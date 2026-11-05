<?php

namespace App\Actions;

use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Http\UploadedFile;

/**
 * Foto-bewijs opslaan (briefing §9), vanaf de website (PhotoController) of de app: gekoppeld aan fase,
 * werkpakket en/of checklistitem, met audit en een regel in het klantdossier.
 */
class StorePhotos
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array{project_phase_id?: int|null, checklist_item_id?: int|null, caption?: string|null, client_visible?: bool|null}  $attributes
     */
    public function handle(Project $project, array $files, User $user, ?WorkPackage $workPackage = null, array $attributes = []): int
    {
        // Checklistitem moet bij het werkpakket horen.
        if (isset($attributes['checklist_item_id'])
            && $workPackage?->items()->whereKey($attributes['checklist_item_id'])->doesntExist()) {
            abort(422, 'Checklistitem hoort niet bij dit werkpakket.');
        }

        foreach ($files as $file) {
            $project->photos()->create([
                'project_phase_id' => $attributes['project_phase_id'] ?? $workPackage?->project_phase_id,
                'work_package_id' => $workPackage?->id,
                'checklist_item_id' => $attributes['checklist_item_id'] ?? null,
                'uploaded_by' => $user->id,
                'path' => $file->store('project-photos'),
                'caption' => $attributes['caption'] ?? null,
                'client_visible' => (bool) ($attributes['client_visible'] ?? true),
            ]);
        }

        $count = count($files);

        AuditLog::record($project, 'fotos_toegevoegd', [], [
            'aantal' => $count,
            'werkpakket' => $workPackage?->name,
        ]);
        $project->customer->recordEvent(
            TimelineEventType::Document,
            $count.' '.($count === 1 ? 'foto' : 'foto\'s').' toegevoegd'.($workPackage ? ' bij '.$workPackage->name : ''),
            null,
            $project,
        );

        return $count;
    }
}
