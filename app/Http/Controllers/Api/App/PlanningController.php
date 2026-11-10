<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\ScheduleEntryType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PlanningController as WebPlanningController;
use App\Http\Requests\StoreScheduleEntryRequest;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Planning in de iOS/Android-app: dezelfde week als de planningpagina van de website. */
class PlanningController extends Controller
{
    /** Vaste kleur per persoon, zoals de avatars op de planningpagina in de app. */
    private const COLORS = ['#0D1A2E', '#F26A1B', '#3E6BA8', '#C2506D', '#1E8A5A', '#7C5CD6'];

    public function index(Request $request, WebPlanningController $planning): JsonResponse
    {
        $request->validate(['week' => ['nullable', 'date']]);

        $user = $request->user();
        $planner = $user->can('manage-crm');
        ['start' => $start, 'days' => $days, 'entries' => $entries, 'rows' => $rows] = $planning->week($request);

        return response()->json([
            'week' => $start->toDateString(),
            'label' => 'Week van '.$start->translatedFormat('j M'),
            'previous' => $start->copy()->subWeek()->toDateString(),
            'next' => $start->copy()->addWeek()->toDateString(),
            'current' => $start->isSameDay(today()->startOfWeek()),
            'days' => $days->map(fn ($day) => [
                'date' => $day->toDateString(),
                'short' => $day->translatedFormat('D'),
                'day' => $day->day,
                'today' => $day->isToday(),
                'past' => $day->lt(today()),
                'heading' => ($day->isToday() ? 'Vandaag, ' : '').$day->translatedFormat('l j F'),
                'name' => $day->isToday() ? 'Vandaag' : ($day->isTomorrow() ? 'Morgen' : Str::ucfirst($day->translatedFormat('l'))),
                'date_label' => $day->translatedFormat('j F'),
            ])->values(),
            'rows' => $rows->map(fn (User $row) => [
                'id' => $row->id,
                'name' => Str::before($row->name, ' '),
                'role' => $row->role->label(),
                'initials' => collect(explode(' ', $row->name))->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode(''),
                'color' => self::COLORS[crc32($row->name) % count(self::COLORS)],
            ])->values(),
            'entries' => $entries->map(fn (ScheduleEntry $entry) => [
                ...$planning->detail($entry, $user),
                // De app heeft lichte schermen; de website (detail) gebruikt de donkere kleuren.
                'classes' => $entry->type->appBlockClasses(),
                'id' => $entry->id,
                'user_id' => $entry->user_id,
                'day' => $entry->date->toDateString(),
                // Relatief: de app opent het project als serverpagina.
                'project' => $entry->project ? route('projects.show', $entry->project, false) : null,
                'delete' => $planner,
            ])->values(),
            'form' => $planner ? [
                'users' => User::where('role', '!=', 'klant')->orderBy('name')->get(['id', 'name']),
                'projects' => Project::active()->orderBy('name')->get(['id', 'name']),
                'types' => array_map(fn (ScheduleEntryType $type) => ['value' => $type->value, 'label' => $type->label()], ScheduleEntryType::cases()),
            ] : null,
        ]);
    }

    public function store(StoreScheduleEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (empty($validated['customer_id']) && ! empty($validated['project_id'])) {
            $validated['customer_id'] = Project::find($validated['project_id'])?->customer_id;
        }

        $entry = ScheduleEntry::create($validated);

        return response()->json(['id' => $entry->id], 201);
    }

    public function destroy(ScheduleEntry $scheduleEntry): Response
    {
        $scheduleEntry->delete();

        return response()->noContent();
    }
}
