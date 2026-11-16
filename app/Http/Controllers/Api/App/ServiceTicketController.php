<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nazorg en service in de app (service/index, alleen projectleiding): de cijfers, de meldingen per status
 * en wat nodig is om een melding aan te nemen of bij te werken. Aannemen en bijwerken gaan via de
 * ServiceTicketController van de website (JSON-antwoord).
 */
class ServiceTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filter = $request->string('status')->toString();

        $tickets = ServiceTicket::with(['project', 'customer', 'assignee'])
            ->when($filter !== '', fn ($q) => $q->where('status', $filter))
            ->orderByRaw("case when priority = 'spoed' then 0 when status = 'nieuw' then 1 else 2 end")
            ->latest()
            ->get();

        $open = ServiceTicket::open()->get();
        $counts = ServiceTicket::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $oldest = (int) ($open->max(fn (ServiceTicket $ticket) => $ticket->daysOpen()) ?: 0);
        $urgent = $open->where('priority', 'spoed')->count();
        $options = fn (array $list) => collect($list)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values();

        return response()->json([
            'kpis' => [
                'open' => $open->count(),
                'urgent' => $urgent,
                'urgent_sub' => $urgent > 0 ? 'lekkage of onveilig' : 'niets dringend',
                'oldest' => $oldest.' '.($oldest === 1 ? 'dag' : 'dagen'),
                'resolved' => (int) ($counts[ServiceTicket::STATUS_RESOLVED] ?? 0),
            ],
            'filter' => $filter,
            'filters' => collect(ServiceTicket::STATUSES)
                ->filter(fn (string $label, string $value) => ($counts[$value] ?? 0) > 0 || $filter === $value)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label, 'count' => (int) ($counts[$value] ?? 0)])
                ->prepend(['value' => '', 'label' => 'Alles', 'count' => (int) $counts->sum()])
                ->values(),
            'tickets' => $tickets->map(function (ServiceTicket $ticket) {
                $days = $ticket->daysOpen();

                return [
                    'id' => $ticket->id,
                    'number' => $ticket->number,
                    'title' => $ticket->title,
                    'description' => $ticket->description,
                    'status' => ['value' => $ticket->status, 'label' => $ticket->statusLabel(), 'classes' => $ticket->appBadgeClasses()],
                    'urgent' => $ticket->priority === 'spoed',
                    'open' => $ticket->isOpen(),
                    'warranty' => (bool) $ticket->warranty,
                    'warranty_note' => $ticket->warranty_note,
                    'meta' => collect([
                        $ticket->number,
                        $ticket->customer?->name,
                        $ticket->project?->name,
                        ServiceTicket::CHANNELS[$ticket->reported_via] ?? $ticket->reported_via,
                        $days.' '.($days === 1 ? 'dag' : 'dagen').' '.($ticket->isOpen() ? 'open' : 'doorlooptijd'),
                        $ticket->assignee?->name,
                    ])->filter()->join(', '),
                    'resolution' => $ticket->resolution,
                    'form' => [
                        'status' => $ticket->status,
                        'assigned_to' => $ticket->assigned_to ? (string) $ticket->assigned_to : '',
                        'planned_on' => $ticket->planned_on?->toDateString() ?? '',
                        'resolution' => $ticket->resolution ?? '',
                        'warranty' => (bool) $ticket->warranty,
                    ],
                ];
            })->values(),
            'statuses' => $options(ServiceTicket::STATUSES),
            'priorities' => $options(ServiceTicket::PRIORITIES),
            'channels' => $options(ServiceTicket::CHANNELS),
            'people' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::with('customer')->orderBy('name')->get()->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name.($project->customer ? ', '.$project->customer->name : ''),
            ])->values(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
