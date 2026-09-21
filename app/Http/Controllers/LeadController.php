<?php

namespace App\Http\Controllers;

use App\Enums\ActionSource;
use App\Enums\LeadStatus;
use App\Enums\TimelineEventType;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $view = $request->query('view', 'kanban');

        $leads = Lead::with(['customer', 'assignee'])
            ->withCount('quotes')
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();

        $columns = collect(LeadStatus::pipeline())
            ->mapWithKeys(fn (LeadStatus $status) => [
                $status->value => $leads->where('status', $status)->values(),
            ]);

        return view('leads.index', [
            'view' => $view,
            'leads' => $leads,
            'columns' => $columns,
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
        $lead->load(['customer.timelineEvents.user', 'assignee', 'quotes', 'project', 'tasks' => fn ($query) => $query->open()]);

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
                ->only(['service', 'description', 'value', 'assigned_to', 'last_contact_at', 'next_action', 'next_action_at'])
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
