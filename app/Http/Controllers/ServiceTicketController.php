<?php

namespace App\Http\Controllers;

use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Nazorg en service: meldingen na de oplevering. De eerste vraag is altijd of
 * het onder garantie valt, want dat bepaalt voor wiens rekening het werk komt.
 */
class ServiceTicketController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('status')->toString();

        $tickets = ServiceTicket::with(['project', 'customer', 'assignee'])
            ->when($filter !== '', fn ($q) => $q->where('status', $filter))
            ->orderByRaw("case when priority = 'spoed' then 0 when status = 'nieuw' then 1 else 2 end")
            ->latest()
            ->get();

        $open = ServiceTicket::open()->get();

        return view('service.index', [
            'tickets' => $tickets,
            'filter' => $filter,
            'counts' => ServiceTicket::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'openCount' => $open->count(),
            'urgent' => $open->where('priority', 'spoed')->count(),
            // Hoe lang de oudste openstaande melding al wacht.
            'oldest' => $open->max(fn (ServiceTicket $ticket) => $ticket->daysOpen()) ?: 0,
            'projects' => Project::with('customer')->orderBy('name')->get(),
            'people' => User::query()->where('active', true)->orderBy('name')->get(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'exists:projects,id'],
            'customer_id' => ['required_without:project_id', 'nullable', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', 'string', 'in:spoed,hoog,normaal,laag'],
            'reported_via' => ['required', 'string', 'in:telefoon,mail,portaal,whatsapp'],
        ]);

        $project = isset($validated['project_id']) ? Project::find($validated['project_id']) : null;

        $ticket = ServiceTicket::create([
            ...$validated,
            'number' => ServiceTicket::nextNumber(),
            'customer_id' => $validated['customer_id'] ?? $project?->customer_id,
            'status' => ServiceTicket::STATUS_NEW,
            'reported_by' => $request->user()->id,
        ]);

        // Of het onder garantie valt, rekenen we uit de opleverdatum.
        $within = $ticket->withinWarrantyPeriod();

        $ticket->update([
            'warranty' => $within ?? true,
            'warranty_note' => match ($within) {
                true => 'Binnen de garantietermijn van '.($project->warranty_months ?? 12).' maanden.',
                false => 'Buiten de garantietermijn. Eerst met de klant bespreken wie dit betaalt.',
                null => 'Geen opleverdatum bekend, garantie nog te bepalen.',
            },
        ]);

        AuditLog::record($ticket, 'servicemelding aangenomen', [], [
            'melding' => $ticket->title,
            'garantie' => $ticket->warranty ? 'ja' : 'nee',
            'prioriteit' => $ticket->priority,
        ]);

        $ticket->customer->recordEvent(
            TimelineEventType::Intern,
            "Servicemelding {$ticket->number}: {$ticket->title}",
            $ticket->description,
            $ticket,
        );

        return back()->with('success', "Melding {$ticket->number} aangenomen. ".$ticket->warranty_note);
    }

    public function update(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:nieuw,gepland,bezig,afgehandeld,afgewezen'],
            'priority' => ['sometimes', 'string', 'in:spoed,hoog,normaal,laag'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'planned_on' => ['nullable', 'date'],
            'warranty' => ['nullable', 'boolean'],
            'warranty_note' => ['nullable', 'string', 'max:255'],
            'resolution' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($request->has('warranty')) {
            $validated['warranty'] = $request->boolean('warranty');
        }

        if (in_array($validated['status'] ?? '', [ServiceTicket::STATUS_RESOLVED, ServiceTicket::STATUS_DECLINED], true)) {
            $validated['resolved_at'] = $serviceTicket->resolved_at ?? now();
        }

        $old = $serviceTicket->only(array_keys($validated));
        $serviceTicket->update($validated);

        AuditLog::record($serviceTicket, 'servicemelding bijgewerkt', $old, $validated);

        if (($validated['status'] ?? null) === ServiceTicket::STATUS_RESOLVED) {
            $serviceTicket->customer->recordEvent(
                TimelineEventType::Intern,
                "Servicemelding {$serviceTicket->number} afgehandeld",
                $serviceTicket->resolution,
                $serviceTicket,
            );
        }

        return back()->with('success', 'Melding bijgewerkt.');
    }
}
