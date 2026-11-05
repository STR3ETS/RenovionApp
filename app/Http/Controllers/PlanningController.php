<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleEntryRequest;
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
    public function index(Request $request): View
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
