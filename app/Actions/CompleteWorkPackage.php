<?php

namespace App\Actions;

use App\Enums\PhaseStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\WorkPackage;
use Illuminate\Validation\ValidationException;

/**
 * Werkpakket afronden (mockup "Taak afronden"), vanaf de website of de app: kan pas als de checklist
 * compleet is en het verplichte foto-bewijs er is. Heropenen kan altijd.
 */
class CompleteWorkPackage
{
    public function handle(WorkPackage $workPackage): void
    {
        $workPackage->load('items');

        if (! $workPackage->checklistComplete()) {
            throw ValidationException::withMessages([
                'checklist' => 'Nog niet alle checklistitems zijn afgevinkt - rond eerst de checklist af.',
            ]);
        }

        // Briefing §9: zonder verplicht foto-bewijs kan de taak niet definitief dicht.
        if (! $workPackage->evidenceComplete()) {
            throw ValidationException::withMessages([
                'photos' => 'Het verplichte foto-bewijs is nog niet compleet - upload eerst de ontbrekende foto\'s.',
            ]);
        }

        $workPackage->update([
            'status' => PhaseStatus::Gereed,
            'completed_at' => now(),
        ]);

        AuditLog::record($workPackage, 'afgerond', [], ['name' => $workPackage->name]);
        $workPackage->project->customer->recordEvent(
            TimelineEventType::Projectupdate,
            'Werkpakket afgerond: '.$workPackage->name,
            null,
            $workPackage,
        );

        $workPackage->project->syncProgress();
    }

    /** Heropenen na (per ongeluk) afronden. */
    public function reopen(WorkPackage $workPackage): void
    {
        $workPackage->update([
            'status' => PhaseStatus::Bezig,
            'completed_at' => null,
        ]);

        AuditLog::record($workPackage, 'heropend', [], ['name' => $workPackage->name]);
        $workPackage->project->syncProgress();
    }
}
