<?php

namespace App\Automations;

use App\Enums\ActionSource;
use App\Enums\PhaseStatus;
use App\Enums\TaskPriority;
use App\Models\AutomationRun;
use App\Models\ProjectPhase;
use App\Models\Task;

/**
 * Briefing §12: "Wacht op klant → stuur reminder; maak follow-up."
 * Fase staat 3+ dagen op "wacht op klant" → herinnertaak voor de projectleider.
 */
class WaitingOnClientAutomation extends BaseAutomation
{
    public function key(): string
    {
        return 'wacht-op-klant';
    }

    public function name(): string
    {
        return 'Wacht op klant';
    }

    public function description(): string
    {
        return 'Fase staat 3+ dagen op "wacht op klant" → herinnertaak om de klant na te bellen.';
    }

    public function run(): int
    {
        $count = 0;

        $phases = ProjectPhase::where('status', PhaseStatus::WachtOpKlant)
            ->where('updated_at', '<=', now()->subDays(3))
            ->whereHas('project', fn ($query) => $query->active())
            ->with('project.customer')
            ->get();

        foreach ($phases as $phase) {
            if (! AutomationRun::claim($this->key(), $phase)) {
                continue;
            }

            $taak = [
                'title' => 'Klant herinneren: '.$phase->project->customer->name.' — keuze/actie voor fase "'.$phase->name.'"',
                'project_id' => $phase->project_id,
                'customer_id' => $phase->project->customer_id,
                'owner_id' => $phase->responsible_id ?? $phase->project->project_leader_id,
                'deadline' => today()->toDateString(),
                'priority' => TaskPriority::Hoog->value,
            ];

            $this->act(
                'Project '.$phase->project->name.' wacht al 3+ dagen op de klant bij fase "'.$phase->name.'".',
                fn () => Task::create([...$taak, 'priority' => TaskPriority::Hoog, 'source' => ActionSource::Automation]),
                ['type' => 'create_task', 'params' => $taak],
                route('projects.show', $phase->project),
            );

            $count++;
        }

        return $count;
    }
}
