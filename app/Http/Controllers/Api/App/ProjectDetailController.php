<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\PhaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkPackage;
use App\Support\AppPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Projectdossier in de iOS/Android-app: de kop, de fasen, de werkpakketten en wat er openstaat.
 * Werkpakketten openen in de app (Api\App\WorkPackageController); fasebeheer en bewerken als serverpagina.
 */
class ProjectDetailController extends Controller
{
    public function __invoke(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        abort_unless($project->isAccessibleBy($user), 403);

        $project->load([
            'customer',
            'quote',
            'projectLeader',
            'chatChannel',
            'phases.workPackages' => fn ($query) => $query->orderBy('position')->withCount(['items', 'items as items_done_count' => fn ($items) => $items->whereNotNull('done_at')]),
            'tasks' => fn ($query) => $query->open()->with(['customer', 'project'])->orderByRaw('deadline is null, deadline asc'),
            'scheduleEntries' => fn ($query) => $query->whereDate('date', '>=', today())->orderBy('date')->limit(5),
            'scheduleEntries.user',
            'scheduleEntries.project',
        ])->loadCount(['photos']);

        $current = $project->currentPhase();
        $next = $current ? $project->phases->first(fn ($phase) => $phase->position > $current->position) : null;
        $crm = $user->can('manage-crm');
        $path = route('projects.show', $project, false);

        return response()->json([
            ...AppPayload::project($project, $user),
            'address' => collect([$project->address, $project->city])->filter()->implode(', ') ?: null,
            'quote' => $crm ? $project->quote?->number : null,
            'start' => $project->start_date?->translatedFormat('j M Y'),
            'end' => $project->end_date_expected?->translatedFormat('j M Y'),
            'current' => $current ? [
                'step' => $project->phases->search(fn ($phase) => $phase->is($current)) + 1,
                'name' => $current->name,
                'status' => ['label' => $current->status->label(), 'classes' => $current->status->appBadgeClasses()],
                'next' => $current->status !== PhaseStatus::Gereed ? $next?->name : null,
            ] : null,
            'phases' => $project->phases->map(fn ($phase) => [
                'name' => $phase->name,
                'done' => $phase->status === PhaseStatus::Gereed,
                'active' => $current !== null && $phase->is($current),
                'state' => $phase->status->value,
                'status' => $phase->status->label(),
                'open_work' => $phase->workPackages->reject->isDone()->count(),
            ])->values(),
            'open_work' => $project->phases->flatMap->workPackages->reject->isDone()->count(),
            // Werkpakketten per fase (alleen fasen met werk), open in de app op het werkpakketscherm.
            'work' => $project->phases->filter(fn ($phase) => $phase->workPackages->isNotEmpty())->map(fn ($phase) => [
                'phase' => $phase->name,
                'active' => $current !== null && $phase->is($current),
                'packages' => $phase->workPackages->map(fn (WorkPackage $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'done' => $package->isDone(),
                    'status' => ['label' => $package->status->label(), 'classes' => $package->status->appBadgeClasses()],
                    'deadline' => $package->deadline?->toDateString(),
                    'overdue' => $package->isOverdue(),
                    'items' => $package->items_count,
                    'items_done' => $package->items_done_count,
                ])->values(),
            ])->values(),
            'photos' => $project->photos_count,
            'tasks' => $project->tasks->map(fn (Task $task) => AppPayload::task($task))->values(),
            'planning' => $project->scheduleEntries->map(fn ($entry) => AppPayload::scheduleEntry($entry))->values(),
            'links' => [
                'execution' => $path.'?tab=uitvoering',
                'photos' => $path.'?tab=fotos',
                'chat' => $project->chatChannel ? route('chat.show', $project->chatChannel, false) : null,
                'edit' => $crm ? route('projects.edit', $project, false) : null,
                'web' => $path,
            ],
        ]);
    }
}
