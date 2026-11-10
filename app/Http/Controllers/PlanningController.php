<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleEntryRequest;
use App\Models\Absence;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PlanningController extends Controller
{
    /**
     * De weergave: week, maand of capaciteit. De maand laat de blokken over de
     * hele looptijd zien, de capaciteit of iemand te vol of te leeg staat.
     */
    public function index(Request $request): View
    {
        return match ($request->string('weergave')->toString()) {
            'maand' => $this->month($request),
            'capaciteit' => $this->capacity($request),
            default => $this->weekView($request),
        };
    }

    private function weekView(Request $request): View
    {
        ['start' => $start, 'days' => $days, 'entries' => $entries, 'rows' => $rows] = $this->week($request);

        return view('planning.index', [
            'start' => $start,
            'days' => $days,
            'details' => $entries->mapWithKeys(fn (ScheduleEntry $entry) => [$entry->id => $this->detail($entry, $request->user())]),
            'entries' => $entries->groupBy(fn (ScheduleEntry $entry) => $entry->user_id.'|'.$entry->date->toDateString()),
            'rows' => $rows,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'projects' => Project::active()->orderBy('name')->get(['id', 'name']),
            'view' => 'week',
        ]);
    }

    /**
     * De werkweek (ma t/m vr) met de planning en de rijen per persoon; ook gebruikt door de app.
     *
     * @return array{start: Carbon, days: Collection<int, Carbon>, entries: Collection<int, ScheduleEntry>, rows: Collection<int, User>}
     */
    public function week(Request $request): array
    {
        $start = $request->filled('week')
            ? Carbon::parse($request->query('week'))->startOfWeek()
            : today()->startOfWeek();

        $days = collect(range(0, 4))->map(fn (int $offset) => $start->copy()->addDays($offset));

        $entries = ScheduleEntry::whereBetween('date', [$start, $start->copy()->addDays(4)])
            ->when($request->user()->cannot('manage-crm'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['user', 'project', 'customer', 'lead'])
            ->orderByRaw('start_time is null, start_time asc')
            ->get();

        if ($request->user()->cannot('manage-crm')) {
            // Uitvoerders zien alleen hun eigen planning.
            $rows = collect([$request->user()]);
        } else {
            $uitvoerders = User::uitvoerders()->orderBy('name')->get();

            // Toon ook rijen voor niet-uitvoerders (bijv. sales-afspraken) met entries deze week.
            $rows = $uitvoerders->merge(
                $entries->pluck('user')->unique('id')->reject(
                    fn (User $user) => $uitvoerders->contains('id', $user->id)
                )
            );
        }

        return ['start' => $start, 'days' => $days, 'entries' => $entries, 'rows' => $rows];
    }

    /**
     * Gegevens voor het detailpaneel op mobiel (tikken op een planningsblok).
     *
     * @return array<string, string|null>
     */
    public function detail(ScheduleEntry $entry, User $viewer): array
    {
        $address = $entry->project?->address
            ? collect([$entry->project->address, $entry->project->city])->filter()->implode(', ')
            : collect([$entry->customer?->address, $entry->customer?->postal_code, $entry->customer?->city])->filter()->implode(', ');

        return [
            'title' => $entry->displayTitle(),
            'type' => $entry->type->label(),
            'classes' => $entry->type->blockClasses(),
            'user' => $entry->user?->name,
            'date' => $entry->date->translatedFormat('l j F'),
            'time' => $entry->start_time
                ? substr($entry->start_time, 0, 5).($entry->end_time ? '-'.substr($entry->end_time, 0, 5) : '')
                : 'Hele dag',
            'notes' => $entry->notes,
            'address' => $address ?: null,
            'maps' => $address ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($address) : null,
            'project' => $entry->project ? route('projects.show', $entry->project) : null,
            'delete' => $viewer->can('manage-crm') ? route('planning.destroy', $entry) : null,
        ];
    }

    /**
     * De maandweergave: alle blokken die deze maand raken, per medewerker op een
     * balk. Zo zie je of iemand drie weken aan één project vastzit.
     */
    private function month(Request $request): View
    {
        $month = $request->filled('maand')
            ? Carbon::parse($request->query('maand').'-01')->startOfMonth()
            : today()->startOfMonth();

        $end = $month->copy()->endOfMonth();

        $entries = ScheduleEntry::query()
            ->where('date', '<=', $end)
            // Een blok zonder einddatum duurt één dag.
            ->where(fn ($query) => $query
                ->where('end_date', '>=', $month)
                ->orWhere(fn ($sub) => $sub->whereNull('end_date')->where('date', '>=', $month)))
            ->when($request->user()->cannot('manage-crm'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['user', 'project'])
            ->orderBy('date')
            ->get();

        $people = $request->user()->can('manage-crm')
            ? User::uitvoerders()->orderBy('name')->get()->merge($entries->pluck('user')->filter()->unique('id'))->unique('id')
            : collect([$request->user()]);

        $days = (int) $month->daysInMonth;

        return view('planning.month', [
            'month' => $month,
            'days' => $days,
            'view' => 'maand',
            'rows' => $people->map(fn (User $person) => [
                'person' => $person,
                'bars' => $entries->where('user_id', $person->id)->map(function (ScheduleEntry $entry) use ($month, $days) {
                    $from = $entry->date->greaterThan($month) ? $entry->date : $month;
                    $until = ($entry->end_date ?? $entry->date);
                    $until = $until->greaterThan($month->copy()->endOfMonth()) ? $month->copy()->endOfMonth() : $until;

                    return [
                        'entry' => $entry,
                        // Procenten over de maandbreedte, zodat de balk meeschaalt.
                        'left' => round(($from->day - 1) / $days * 100, 3),
                        'width' => round(($until->day - $from->day + 1) / $days * 100, 3),
                    ];
                })->values(),
            ])->values(),
        ]);
    }

    /**
     * De capaciteitsweergave: per medewerker de bezetting voor vier weken.
     *
     * Een bezetting boven honderd procent is geen prestatie maar een probleem:
     * dan staat er meer ingepland dan iemand in zijn uren kan doen.
     */
    private function capacity(Request $request): View
    {
        $start = $request->filled('week')
            ? Carbon::parse($request->query('week'))->startOfWeek()
            : today()->startOfWeek();

        $weeks = collect(range(0, 3))->map(fn (int $i) => $start->copy()->addWeeks($i));
        $people = User::query()->where('active', true)->orderBy('name')->get()->filter->isPlannable();

        $entries = ScheduleEntry::query()
            ->where('date', '<=', $start->copy()->addWeeks(4))
            ->where(fn ($query) => $query
                ->where('end_date', '>=', $start)
                ->orWhere(fn ($sub) => $sub->whereNull('end_date')->where('date', '>=', $start)))
            ->with('project')
            ->get()
            ->groupBy('user_id');

        $absences = Absence::where('until', '>=', $start)->get()->groupBy('user_id');

        return view('planning.capacity', [
            'start' => $start,
            'weeks' => $weeks,
            'view' => 'capaciteit',
            'rows' => $people->map(fn (User $person) => [
                'person' => $person,
                'weeks' => $weeks->map(function (Carbon $week) use ($person, $entries, $absences) {
                    $end = $week->copy()->addDays(4);

                    $planned = $entries->get($person->id, collect())->sum(function (ScheduleEntry $entry) use ($week, $end) {
                        $from = $entry->date->greaterThan($week) ? $entry->date : $week;
                        $until = ($entry->end_date ?? $entry->date);
                        $until = $until->lessThan($end) ? $until : $end;

                        return $from->greaterThan($until) ? 0 : ((int) $from->diffInWeekdays($until) + 1) * 8;
                    });

                    $away = $absences->get($person->id, collect())
                        ->sum(fn (Absence $absence) => $absence->workdaysIn($week, $end));

                    $available = max(0, $person->weekly_capacity - $away * 8);

                    return [
                        'planned' => (int) $planned,
                        'available' => $available,
                        'absent' => (int) $away,
                        'percentage' => $available > 0 ? (int) round($planned / $available * 100) : 0,
                    ];
                })->values(),
            ])->values(),
        ]);
    }

    public function store(StoreScheduleEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['customer_id']) && ! empty($validated['project_id'])) {
            $validated['customer_id'] = Project::find($validated['project_id'])?->customer_id;
        }

        ScheduleEntry::create($validated);

        return back()->with('success', 'Planning toegevoegd.');
    }

    public function destroy(ScheduleEntry $scheduleEntry): RedirectResponse
    {
        $scheduleEntry->delete();

        return back()->with('success', 'Planningsregel verwijderd.');
    }
}
