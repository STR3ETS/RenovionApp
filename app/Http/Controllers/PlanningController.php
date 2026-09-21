<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleEntryRequest;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function index(Request $request): View
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
            // Vakmensen zien alleen hun eigen planning.
            $rows = collect([$request->user()]);
        } else {
            $vakmensen = User::vakmensen()->orderBy('name')->get();

            // Toon ook rijen voor niet-vakmensen (bijv. sales-afspraken) met entries deze week.
            $rows = $vakmensen->merge(
                $entries->pluck('user')->unique('id')->reject(
                    fn (User $user) => $vakmensen->contains('id', $user->id)
                )
            );
        }

        return view('planning.index', [
            'start' => $start,
            'days' => $days,
            'entries' => $entries->groupBy(fn (ScheduleEntry $entry) => $entry->user_id.'|'.$entry->date->toDateString()),
            'rows' => $rows,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'projects' => Project::active()->orderBy('name')->get(['id', 'name']),
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
