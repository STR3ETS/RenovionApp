<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Checklistitem afvinken of weer openzetten, vanaf de website (ChecklistItemController) of de app
 * (Api\App\WorkPackageController). Zonder het verplichte foto-bewijs kan het item niet af (briefing §9).
 */
class ToggleChecklistItem
{
    public function handle(ChecklistItem $item, User $user): void
    {
        if (! $item->isDone() && ! $item->hasRequiredPhotos()) {
            $missing = $item->requires_photos - $item->photos()->count();

            throw ValidationException::withMessages([
                'item' => '"'.$item->label.'" vereist nog '.$missing.' '.($missing === 1 ? 'bewijsfoto' : 'bewijsfoto\'s').' - upload die eerst.',
            ]);
        }

        $item->update($item->isDone()
            ? ['done_at' => null, 'done_by' => null]
            : ['done_at' => now(), 'done_by' => $user->id]);

        AuditLog::record($item, $item->isDone() ? 'afgevinkt' : 'heropend', [], ['label' => $item->label]);
    }
}
