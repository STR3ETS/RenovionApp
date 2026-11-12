<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\LeadQualification;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Calculation;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\Task;
use App\Models\TimelineEvent;
use App\Support\AppPayload;
use Illuminate\Http\JsonResponse;

/**
 * Aanvragen in de app: de pipeline (zoals de Kanban van leads/index, per kolom) en de aanvraag zelf
 * (leads/show). Status wijzigen en contactmomenten gaan via dezelfde controllers als de website.
 */
class LeadController extends Controller
{
    public function index(): JsonResponse
    {
        $leads = Lead::with(['customer', 'assignee', ...(config('renovion.modules.quotes') ? ['quotes'] : [])])
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'open' => $leads->filter(fn (Lead $lead) => $lead->status->isOpen())->count(),
            'columns' => collect(LeadStatus::pipeline())->map(fn (LeadStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'leads' => $leads->where('status', $status)->map(fn (Lead $lead) => $this->card($lead))->values(),
            ]),
            'inactive' => $leads->whereIn('status', [LeadStatus::Verloren, LeadStatus::OnHold])->map(fn (Lead $lead) => $this->card($lead))->values(),
            'links' => ['create' => route('leads.create', [], false)],
        ]);
    }

    public function show(Lead $lead): JsonResponse
    {
        $quotes = (bool) config('renovion.modules.quotes');

        $lead->load([
            'customer.timelineEvents' => fn ($query) => $query->with('user')->limit(15),
            'assignee',
            'calculations.lines',
            'tasks' => fn ($query) => $query->open()->with('owner'),
            ...($quotes ? ['quotes'] : []),
        ]);
        $customer = $lead->customer;

        $signals = array_values(array_filter([
            $lead->missingPhone() ? ['color' => 'amber', 'text' => 'Geen telefoonnummer bekend'] : null,
            $lead->next_action_at?->isPast() ? ['color' => 'red', 'text' => 'Opvolging verlopen: '.($lead->next_action ?? 'actie').' ('.$lead->next_action_at->translatedFormat('j M H:i').')'] : null,
            $lead->isSilent() ? ['color' => 'amber', 'text' => 'Geen volgende actie gepland en al '.((int) ($lead->last_contact_at ?? $lead->created_at)->diffInDays()).' dagen geen contact'] : null,
            $lead->status->isOpen() && $lead->qualification === LeadQualification::Onbeoordeeld ? ['color' => 'gray', 'text' => 'Nog niet gekwalificeerd: beoordeel de aanvraag (koud/warm/heet)'] : null,
        ]));

        return response()->json([
            'id' => $lead->id,
            'name' => $customer->name,
            'subtitle' => ($lead->service ?? 'Aanvraag').', '.($customer->city ?? 'plaats onbekend'),
            'status' => ['value' => $lead->status->value, 'label' => $lead->status->label(), 'classes' => $lead->status->appBadgeClasses()],
            'statuses' => array_map(fn (LeadStatus $status) => ['value' => $status->value, 'label' => $status->label(), 'classes' => $status->appBadgeClasses()], LeadStatus::cases()),
            'signals' => $signals,
            'details' => [
                'service' => $lead->service,
                'value' => $lead->value !== null ? (float) $lead->value : null,
                'qualification' => ['label' => $lead->qualification->label(), 'classes' => $lead->qualification->appBadgeClasses()],
                'desired_start' => $lead->desired_start,
                'source' => $lead->source->label(),
                'created_at' => $lead->created_at->toIso8601String(),
                'assignee' => $lead->assignee?->name,
                'last_contact_at' => $lead->last_contact_at?->toIso8601String(),
                'next_action' => $lead->next_action,
                'next_action_at' => $lead->next_action_at?->toIso8601String(),
                'description' => $lead->description,
            ],
            'customer' => [
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'postal_city' => trim(($customer->postal_code ?? '').' '.($customer->city ?? '')) ?: null,
                'path' => route('customers.show', $customer, false),
            ],
            'calculations' => $lead->calculations->map(fn (Calculation $calculation) => [
                'id' => $calculation->id,
                'title' => $calculation->title,
                'sub' => '€ '.number_format($calculation->totalExcl(), 2, ',', '.').' excl. btw, '.$calculation->lines->count().' regels',
                'status' => ['label' => $calculation->status->label(), 'classes' => $calculation->status->appBadgeClasses()],
                'path' => route('calculations.show', $calculation, false),
            ]),
            'quotes' => $quotes ? $lead->quotes->map(fn (Quote $quote) => [
                'id' => $quote->id,
                'number' => $quote->number,
                'sub' => '€ '.number_format((float) $quote->total, 2, ',', '.').($quote->daysOpen() ? ', '.$quote->daysOpen().' dagen open' : ''),
                'status' => ['label' => $quote->status->label(), 'classes' => $quote->status->appBadgeClasses()],
                'path' => route('quotes.show', $quote, false),
            ]) : null,
            'tasks' => $lead->tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'sub' => ($task->deadline?->translatedFormat('j M') ?? 'geen deadline').', '.($task->owner?->name ?? '-'),
            ]),
            'timeline' => $customer->timelineEvents->map(fn (TimelineEvent $event) => AppPayload::timelineEvent($event)),
            'links' => [
                'web' => route('leads.show', $lead, false),
                'edit' => route('leads.edit', $lead, false),
                'calculation' => route('calculations.create', ['aanvraag' => $lead->id], false),
                'quote' => $quotes ? route('quotes.create', ['aanvraag' => $lead->id], false) : null,
            ],
        ]);
    }

    /** Een kaart in de pipeline, met dezelfde signalen als x-lead-card. @return array<string, mixed> */
    private function card(Lead $lead): array
    {
        $followUp = match (true) {
            $lead->next_action_at?->isPast() && ! $lead->next_action_at->isToday() => ['color' => 'red', 'text' => 'Opvolging verlopen ('.$lead->next_action_at->translatedFormat('j M').')'],
            $lead->needsFollowUpToday() => ['color' => 'amber', 'text' => 'Vandaag opvolgen'],
            $lead->isSilent() => ['color' => 'amber', 'text' => 'Geen opvolging gepland'],
            default => null,
        };

        $openQuotes = $lead->relationLoaded('quotes')
            ? $lead->quotes->filter(fn (Quote $quote) => $quote->status->isOpen() && $quote->daysOpen() !== null && $quote->daysOpen() >= 3)
                ->map(fn (Quote $quote) => ['color' => 'amber', 'text' => 'Offerte '.$quote->daysOpen().' dagen open'.($quote->viewed_count > 0 ? ', '.$quote->viewed_count.'× bekeken' : '')])
                ->all()
            : [];

        return [
            'id' => $lead->id,
            'name' => $lead->customer->name,
            'sub' => collect([$lead->customer->city ?? 'Plaats onbekend', $lead->service])->filter()->implode(', '),
            'value' => $lead->value !== null ? (float) $lead->value : null,
            'phone' => $lead->customer->phone,
            'assignee' => $lead->assignee?->name,
            'signals' => array_values(array_filter([
                $lead->missingPhone() ? ['color' => 'amber', 'text' => 'Geen telefoonnummer'] : null,
                $followUp,
                $lead->status->isOpen() && $lead->qualification === LeadQualification::Onbeoordeeld ? ['color' => 'gray', 'text' => 'Nog kwalificeren'] : null,
                ...$openQuotes,
            ])),
            'qualification' => $lead->qualification === LeadQualification::Onbeoordeeld ? null
                : ['label' => $lead->qualification->label(), 'classes' => $lead->qualification->appBadgeClasses()],
            'lost_reason' => $lead->lost_reason,
            'status' => ['label' => $lead->status->label(), 'classes' => $lead->status->appBadgeClasses()],
            'path' => route('leads.show', $lead, false),
        ];
    }
}
