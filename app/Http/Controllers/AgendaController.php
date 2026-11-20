<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\Services\AgendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * De agenda per medewerker: dag, week en team. Afspraken, taken met een tijd,
 * belacties uit de opvolging en werk uit de planning staan op één raster.
 */
class AgendaController extends Controller
{
    public const VIEWS = ['dag' => 'Dag', 'week' => 'Week', 'team' => 'Team'];

    public function index(Request $request, AgendaService $agenda): View
    {
        $canCrm = $request->user()->can('manage-crm');
        $view = $request->string('weergave', 'dag')->toString();
        $view = array_key_exists($view, self::VIEWS) ? $view : 'dag';

        // De teamweergave is voor wie het werk verdeelt.
        if ($view === 'team' && ! $canCrm) {
            $view = 'week';
        }

        $date = $request->filled('datum') ? Carbon::parse($request->query('datum'))->startOfDay() : today();
        $subject = $this->subject($request, $canCrm);

        $team = $canCrm
            ? User::where('active', true)
                ->whereNotIn('role', collect(UserRole::external())->pluck('value'))
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
            : collect([$subject]);

        [$from, $until] = $view === 'dag'
            ? [$date->copy(), $date->copy()]
            : [$date->copy()->startOfWeek(), $date->copy()->startOfWeek()->addDays(4)];

        $people = $view === 'team' ? $team : collect([$subject]);
        $items = $agenda->items($people, $from, $until);

        // In je eigen agenda horen aanvragen zonder eigenaar niet thuis.
        if ($view !== 'team') {
            $items = $items->where('user_id', $subject->id)->values();
        }

        $days = collect(range(0, (int) $from->diffInDays($until)))->map(fn (int $offset) => $from->copy()->addDays($offset));

        return view('agenda.index', [
            'view' => $view,
            'views' => $canCrm ? self::VIEWS : array_diff_key(self::VIEWS, ['team' => true]),
            'date' => $date,
            'from' => $from,
            'until' => $until,
            'days' => $days,
            'hours' => range(AgendaService::FIRST_HOUR, AgendaService::LAST_HOUR),
            'subject' => $subject,
            'team' => $team,
            'canCrm' => $canCrm,
            'items' => $items,
            'step' => $view === 'dag' ? 1 : 7,
            // Voor het nieuwe-item-venster: de open aanvragen om te bellen.
            'leads' => $canCrm
                ? Lead::open()->with('customer:id,name,phone')->latest()->limit(200)->get(['id', 'customer_id', 'service', 'missed_calls'])
                : collect(),
        ]);
    }

    /** Een afspraak afvinken, of het vinkje weer weghalen. */
    public function toggleDone(Request $request, ScheduleEntry $scheduleEntry): RedirectResponse
    {
        $mine = $scheduleEntry->user_id === $request->user()->id
            || $scheduleEntry->participants()->where('users.id', $request->user()->id)->exists();

        abort_unless($mine || $request->user()->can('manage-crm'), 403);

        $scheduleEntry->update(['done_at' => $scheduleEntry->done_at === null ? now() : null]);

        return back()->with('success', $scheduleEntry->done_at ? 'Afspraak afgevinkt.' : 'Afspraak staat weer open.');
    }

    /**
     * Wiens agenda bekijken we? Standaard je eigen. Wie het werk verdeelt mag
     * met ?medewerker= bij een collega kijken.
     */
    private function subject(Request $request, bool $canCrm): User
    {
        $id = $request->integer('medewerker');

        if ($id === 0 || ! $canCrm) {
            return $request->user();
        }

        return User::findOr($id, fn () => $request->user());
    }
}
