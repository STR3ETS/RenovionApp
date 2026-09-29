<?php

namespace App\Automations;

use App\Enums\ActionSource;
use App\Enums\PhaseStatus;
use App\Enums\TaskPriority;
use App\Models\AutomationRun;
use App\Models\Task;
use App\Models\WorkPackage;

/**
 * Briefing §12: "Deadline nadert → herinner verantwoordelijke."
 * Werkpakket met deadline vandaag/morgen (of al verstreken) en nog niet
 * gereed → remindertaak voor de verantwoordelijke.
 */
class DeadlineReminderAutomation extends BaseAutomation
{
    public function key(): string
    {
        return 'deadline-nadert';
    }

    public function name(): string
    {
        return 'Deadline nadert';
    }

    public function description(): string
    {
        return 'Werkpakket-deadline vandaag/morgen en nog niet gereed → remindertaak voor de verantwoordelijke.';
    }

    public function run(): int
    {
        $count = 0;

        $packages = WorkPackage::where('status', '!=', PhaseStatus::Gereed)
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<=', today()->addDay())
            ->whereHas('project', fn ($query) => $query->active())
            ->with(['project', 'responsible'])
            ->get();

        foreach ($packages as $package) {
            if (! AutomationRun::claim($this->key(), $package)) {
                continue;
            }

            $taak = [
                'title' => 'Deadline nadert: '.$package->name.' ('.$package->deadline->translatedFormat('j M').')',
                'project_id' => $package->project_id,
                'customer_id' => $package->project->customer_id,
                'owner_id' => $package->responsible_id ?? $package->project->project_leader_id,
                'deadline' => $package->deadline->toDateString(),
                'priority' => TaskPriority::Hoog->value,
            ];

            $this->act(
                'Werkpakket "'.$package->name.'" ('.$package->project->name.') moet '.$package->deadline->translatedFormat('j M').' gereed zijn'.($package->responsible ? ' — verantwoordelijke: '.$package->responsible->name : '').'.',
                fn () => Task::create([...$taak, 'priority' => TaskPriority::Hoog, 'source' => ActionSource::Automation]),
                ['type' => 'create_task', 'params' => $taak],
                route('work-packages.show', $package),
            );

            $count++;
        }

        return $count;
    }
}
