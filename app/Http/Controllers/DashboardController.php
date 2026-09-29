<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Services\AttentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Het Vandaag-scherm: actie gaat vóór informatie.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->role === UserRole::Klant) {
            return redirect()->route('portal.index');
        }

        if ($request->user()->cannot('manage-crm')) {
            return view('dashboard.uitvoerder', [
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
            'actieve_projecten' => Project::active()->count(),
            'projecten_aandacht' => Project::active()->whereDate('end_date_expected', '<', today())->count(),
            'nieuwe_aanvragen' => Lead::where('status', LeadStatus::Nieuw)->count(),
            'open_aanvragen' => Lead::open()->count(),
            'open_offertes' => Quote::open()->whereNotNull('sent_at')->count(),
            'taken_deze_week' => Task::open()->where(fn ($query) => $query
                ->whereNull('deadline')
                ->orWhereDate('deadline', '<=', today()->endOfWeek()))->count(),
            'taken_te_laat' => Task::open()->whereDate('deadline', '<', today())->count(),
            'omzet_lopend' => (float) Project::active()->sum('value'),
            'omzet_open' => (float) Project::active()
                ->selectRaw('coalesce(sum(value - paid_amount), 0) as open')
                ->value('open'),
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

        $recenteProjecten = Project::active()
            ->with(['customer', 'craftsmen', 'phases'])
            ->withCount(['photos' => fn ($query) => $query->where('client_visible', true)])
            ->latest('updated_at')
            ->limit(4)
            ->get();

        $attentionItems = (new AttentionService)->items();

        return view('dashboard.index', [
            'stats' => $stats,
            'recenteProjecten' => $recenteProjecten,
            'aandachtItems' => $attentionItems->take(4),
            'attentionCount' => $attentionItems->count(),
            'taken' => $taken,
            'opvolgLeads' => $opvolgLeads,
            'opvolgOffertes' => $opvolgOffertes,
            'betaalRisicos' => $betaalRisicos,
            'planningVandaag' => $planningVandaag,
            'aandachtTotaal' => $taken->count() + $opvolgLeads->count() + $opvolgOffertes->count() + $betaalRisicos->count(),
        ]);
    }
}
