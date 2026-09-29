<?php

namespace App\Automations;

use App\Actions\GenerateDeliveryReport;
use App\Enums\PhaseStatus;
use App\Models\AutomationRun;
use App\Models\ProjectPhase;

/**
 * Briefing §12: "Oplevering → genereer concept-opleverrapport met foto's,
 * restpunten, akkoord en projectdata."
 */
class DeliveryReportConceptAutomation extends BaseAutomation
{
    public function key(): string
    {
        return 'opleverrapport-concept';
    }

    public function name(): string
    {
        return 'Concept-opleverrapport bij oplevering';
    }

    public function description(): string
    {
        return 'Fase "Oplevering" gereed → automatisch een concept-opleverrapport uit fasen, foto-bewijs en restpunten.';
    }

    public function run(): int
    {
        $count = 0;

        $phases = ProjectPhase::where('name', 'Oplevering')
            ->where('status', PhaseStatus::Gereed)
            ->whereHas('project', fn ($query) => $query->doesntHave('deliveryReport'))
            ->with('project.customer')
            ->get();

        foreach ($phases as $phase) {
            if (! AutomationRun::claim($this->key(), $phase)) {
                continue;
            }

            $this->act(
                'Fase "Oplevering" van '.$phase->project->name.' is gereed — tijd voor het opleverrapport.',
                fn () => app(GenerateDeliveryReport::class)->handle($phase->project),
                null,
                route('projects.show', $phase->project),
            );

            $count++;
        }

        return $count;
    }
}
