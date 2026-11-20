<?php

namespace App\Http\Controllers;

use App\Enums\ActionSource;
use App\Enums\LeadStatus;
use App\Enums\QuoteStatus;
use App\Enums\TimelineEventType;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\User;
use App\Support\Dossier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $leads = Lead::with(['customer', 'assignee', 'quotes', 'project'])
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();

        // De verkooptrechter loopt tot en met de offerte; daarna is het een project.
        $open = $leads
            ->filter(fn (Lead $lead) => $lead->status->isOpen() && $lead->project === null)
            ->each(fn (Lead $lead) => $lead->setRelation('dossier', Dossier::forLead($lead)))
            ->filter(fn (Lead $lead) => $lead->dossier->stage() <= 3)
            ->values();

        $value = fn (Lead $lead) => (float) ($lead->dossier->quote?->subtotal ?: $lead->value);

        // Per stap de kans dat het een opdracht wordt, en de status die een kaart krijgt als je hem erheen sleept.
        $steps = [[10, 'contact'], [25, 'intake'], [40, 'offerte'], [60, 'offerte']];

        $columns = collect($steps)->map(fn (array $step, int $index) => [
            'label' => Dossier::STEPS[$index],
            'chance' => $step[0],
            'status' => $step[1],
            'leads' => $open->filter(fn (Lead $lead) => $lead->dossier->stage() === $index)->values(),
        ]);

        // Opvolging: te laat eerst, dan niets gepland, dan vandaag, dan later.
        $rank = ['late' => 0, 'none' => 1, 'today' => 2, 'later' => 3];
        $follow = $open->map(function (Lead $lead) {
            $at = $lead->next_action_at;
            $state = $at === null ? 'none' : ($at->isPast() ? 'late' : ($at->isToday() ? 'today' : 'later'));

            return [
                'lead' => $lead,
                'state' => $state,
                'unreached' => $lead->last_contact_at === null || $lead->isUnreached(),
                'label' => $at === null
                    ? 'Niets gepland'
                    : ucfirst($lead->next_action ?? 'Opvolgen').' '.($at->isToday() ? 'vandaag '.$at->format('H:i') : $at->translatedFormat('D j M H:i')),
            ];
        })->sortBy(fn (array $row) => [$rank[$row['state']], $row['lead']->next_action_at?->timestamp ?? PHP_INT_MAX])->values();

        return view('leads.index', [
            'board' => [
                'columns' => $columns,
                'count' => $open->count(),
                'value' => $open->sum($value),
                'weighted' => $open->sum(fn (Lead $lead) => $value($lead) * $steps[$lead->dossier->stage()][0] / 100),
                'won' => (float) Quote::where('status', QuoteStatus::Akkoord)->whereYear('accepted_at', now()->year)->sum('subtotal'),
            ],
            'follow' => $follow,
            'inactief' => $leads->whereIn('status', [LeadStatus::Verloren, LeadStatus::OnHold])->values(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('leads.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'city']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'selectedCustomerId' => $request->integer('klant') ?: null,
        ]);
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $lead = DB::transaction(function () use ($validated) {
            $customer = isset($validated['customer_id'])
                ? Customer::findOrFail($validated['customer_id'])
                : Customer::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'postal_code' => $validated['postal_code'] ?? null,
                    'city' => $validated['city'] ?? null,
                ]);

            $lead = $customer->leads()->create([
                'service' => $validated['service'] ?? null,
                'description' => $validated['description'] ?? null,
                'source' => ActionSource::Handmatig,
                'status' => LeadStatus::Nieuw,
                'value' => $validated['value'] ?? null,
                'desired_start' => $validated['desired_start'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'next_action' => $validated['next_action'] ?? null,
                'next_action_at' => $validated['next_action_at'] ?? null,
            ]);

            $customer->recordEvent(
                TimelineEventType::Aanvraag,
                'Nieuwe aanvraag: '.($lead->service ?? 'onbekend'),
                $lead->description,
                $lead,
            );

            AuditLog::record($lead, 'aangemaakt', [], ['status' => LeadStatus::Nieuw->value]);

            return $lead;
        });

        return redirect()->route('leads.show', $lead)->with('success', 'Aanvraag aangemaakt.');
    }

    public function show(Lead $lead): View
    {
        $lead->load(['customer.timelineEvents.user', 'assignee', 'quotes', 'calculations.lines', 'project', 'tasks' => fn ($query) => $query->open()]);

        return view('leads.show', [
            'lead' => $lead,
            'timeline' => $lead->customer->timelineEvents->take(15),
        ]);
    }

    public function edit(Lead $lead): View
    {
        $lead->load('customer');

        return view('leads.edit', [
            'lead' => $lead,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $lead) {
            $customer = $lead->customer;
            $phoneWasMissing = blank($customer->phone);

            $customer->update(collect($validated)
                ->only(['name', 'email', 'phone', 'address', 'postal_code', 'city'])
                ->filter(fn ($value, $key) => $key !== 'name' || filled($value))
                ->all());

            $lead->update(collect($validated)
                ->only(['service', 'description', 'value', 'qualification', 'desired_start', 'assigned_to', 'last_contact_at', 'next_action', 'next_action_at'])
                ->all());

            if ($phoneWasMissing && filled($customer->phone)) {
                $lead->forceFill(['phone_requested_at' => null])->save();
                $customer->recordEvent(
                    TimelineEventType::Wijziging,
                    'Telefoonnummer toegevoegd',
                    $customer->phone,
                    $lead,
                );
            }

            if ($lead->wasChanged()) {
                AuditLog::record($lead, 'bijgewerkt', [], $lead->getChanges());
            }
        });

        return redirect()->route('leads.show', $lead)->with('success', 'Aanvraag bijgewerkt.');
    }
}
