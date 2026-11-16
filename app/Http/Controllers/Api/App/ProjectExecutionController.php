<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\PhaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\WorkPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Uitvoering van een project in de app (tab "uitvoering" van het projectdossier): alle fasen met hun gate en de
 * werkpakketten eronder. Kantoor wijzigt de fasestatus, geeft een fase vrij en voegt werkpakketten toe, via
 * dezelfde controllers als de website (ProjectPhaseController, WorkPackageController).
 */
class ProjectExecutionController extends Controller
{
    public function __invoke(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        abort_unless($project->isAccessibleBy($user), 403);

        $project->load([
            'phases.responsible',
            'phases.approver',
            'phases.workPackages' => fn ($query) => $query->orderBy('position')->with('responsible')->withCount(['items', 'items as items_done_count' => fn ($items) => $items->whereNotNull('done_at')]),
            'craftsmen',
            'projectLeader',
        ]);

        $current = $project->currentPhase();
        $crm = $user->can('manage-crm');

        // Wie een werkpakket kan krijgen: de vakmensen op het project en de projectleider (zoals op de website).
        $team = $project->craftsmen->map(fn ($member) => ['id' => $member->id, 'name' => $member->name]);
        if ($project->projectLeader && ! $project->craftsmen->contains('id', $project->project_leader_id)) {
            $team->push(['id' => $project->project_leader_id, 'name' => $project->projectLeader->name]);
        }

        return response()->json([
            'project' => ['id' => $project->id, 'name' => $project->name, 'path' => route('projects.show', $project, false)],
            'can_manage' => $crm,
            'statuses' => $crm ? array_map(fn (PhaseStatus $status) => ['value' => $status->value, 'label' => $status->label()], PhaseStatus::cases()) : [],
            'team' => $crm ? $team->values() : [],
            'phases' => $project->phases->map(fn (ProjectPhase $phase) => [
                'id' => $phase->id,
                'position' => $phase->position,
                'name' => $phase->name,
                'active' => $current !== null && $phase->is($current),
                'done' => $phase->status === PhaseStatus::Gereed,
                'status' => ['value' => $phase->status->value, 'label' => $phase->status->label(), 'classes' => $phase->status->appBadgeClasses()],
                'sub' => collect([
                    $phase->responsible?->name ?? 'Geen verantwoordelijke',
                    $phase->approved_at ? 'vrijgegeven door '.($phase->approver?->name ?? '-').' op '.$phase->approved_at->translatedFormat('j M') : null,
                    $phase->client_approved_at ? 'klant: gezien en akkoord ('.$phase->client_approved_at->translatedFormat('j M').')' : null,
                ])->filter()->implode(', '),
                'packages' => $phase->workPackages->map(fn (WorkPackage $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'done' => $package->isDone(),
                    'overdue' => $package->isOverdue(),
                    'status' => ['label' => $package->status->label(), 'classes' => $package->status->appBadgeClasses()],
                    'sub' => collect([
                        $package->responsible?->name ?? 'Niet toegewezen',
                        $package->deadline?->translatedFormat('j M'),
                        $package->items_count ? 'checklist '.$package->items_done_count.'/'.$package->items_count : null,
                    ])->filter()->implode(', '),
                ])->values(),
            ])->values(),
        ]);
    }
}
