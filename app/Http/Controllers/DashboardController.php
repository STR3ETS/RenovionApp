<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use App\Models\ScheduleEntry;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Het Vandaag-scherm: actie gaat vóór informatie.
     */
    public function __invoke(Request $request): View
    {
        if ($request->user()->cannot('manage-crm')) {
            return view('dashboard.vakman', [
                'taken' => Task::open()
                    ->where('owner_id', $request->user()->id)
                    ->with(['customer', 'project'])
                    ->orderByRaw('deadline is null, deadline asc')
                    ->limit(20)
                    ->get(),
                'planningWeek' => ScheduleEntry::where('user_id', $request->user()->id)
                    ->whereBetween('date', [today(), today()->addDays(7)])
                    ->with(['project', 'customer'])
                    ->orderBy('date')
                    ->orderByRaw('start_time is null, start_time asc')
                    ->get(),
            ]);
        }

        $stats = [
            'nieuwe_aanvragen' => Lead::where('status', LeadStatus::Nieuw)->count(),
            'open_offertes' => Quote::open()->whereNotNull('sent_at')->count(),
            'lopende_projecten' => Project::active()->count(),
            'open_taken' => Task::open()->count(),
            'planningsrisicos' => Project::active()->whereDate('end_date_expected', '<', today())->count(),
            'open_betalingen' => Project::active()
                ->whereNotNull('deposit_amount')
                ->whereNull('deposit_received_at')
                ->count(),
        ];

        $taken = Task::open()
            ->dueToday()
            ->with(['customer', 'project', 'lead'])
            ->orderByRaw('deadline is null, deadline asc')
            ->limit(10)
            ->get();

        $opvolgLeads = Lead::open()
            ->where(function ($query) {
                $query->where('next_action_at', '<=', now()->endOfDay())
                    ->orWhere('status', LeadStatus::Nieuw);
            })
            ->with(['customer', 'assignee'])
            ->orderByRaw('next_action_at is null, next_action_at asc')
            ->limit(10)
            ->get();

        $opvolgOffertes = config('renovion.modules.quotes')
            ? Quote::open()
                ->whereNotNull('sent_at')
                ->where('sent_at', '<=', now()->subDays(3))
                ->with(['customer', 'lead'])
                ->orderBy('sent_at')
                ->limit(10)
                ->get()
            : collect();

        $betaalRisicos = Project::active()
            ->whereNotNull('deposit_amount')
            ->whereNull('deposit_received_at')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', today()->addDays(7))
            ->with('customer')
            ->orderBy('start_date')
            ->get();

        $planningVandaag = ScheduleEntry::whereDate('date', today())
            ->with(['user', 'project', 'customer'])
            ->orderByRaw('start_time is null, start_time asc')
            ->get();

        return view('dashboard.index', [
            'stats' => $stats,
            'taken' => $taken,
            'opvolgLeads' => $opvolgLeads,
            'opvolgOffertes' => $opvolgOffertes,
            'betaalRisicos' => $betaalRisicos,
            'planningVandaag' => $planningVandaag,
            'aandachtTotaal' => $taken->count() + $opvolgLeads->count() + $opvolgOffertes->count() + $betaalRisicos->count(),
        ]);
    }
}
