<?php

namespace App\Automations;

use App\Enums\ActionSource;
use App\Enums\TaskPriority;
use App\Enums\TimelineEventType;
use App\Models\AutomationRun;
use App\Models\Project;
use App\Models\Task;

/**
 * Briefing §18/§22: project start binnenkort maar de aanbetaling is niet
 * ontvangen → waarschuwingstaak met hoge prioriteit.
 */
class DepositWarningAutomation implements Automation
{
    public function key(): string
    {
        return 'aanbetaling-waarschuwing';
    }

    public function name(): string
    {
        return 'Aanbetaling-waarschuwing';
    }

    public function description(): string
    {
        return 'Project start binnen 4 dagen + aanbetaling niet ontvangen → controle-taak met hoge prioriteit.';
    }

    public function run(): int
    {
        $count = 0;

        $projects = Project::active()
            ->whereNotNull('deposit_amount')
            ->whereNull('deposit_received_at')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', today()->addDays(4))
            ->with('customer')
            ->get()
            ->filter(fn (Project $project) => (float) $project->deposit_amount > 0);

        foreach ($projects as $project) {
            if (! AutomationRun::claim($this->key(), $project)) {
                continue;
            }

            Task::create([
                'title' => 'Aanbetaling '.$project->customer->name.' controleren — project start '.$project->start_date->translatedFormat('j M'),
                'customer_id' => $project->customer_id,
                'project_id' => $project->id,
                'owner_id' => $project->project_leader_id,
                'deadline' => today()->toDateString(),
                'priority' => TaskPriority::Hoog,
                'source' => ActionSource::Automation,
            ]);

            $project->customer->recordEvent(
                TimelineEventType::Betaling,
                'Waarschuwing: aanbetaling € '.number_format((float) $project->deposit_amount, 0, ',', '.').' nog niet ontvangen, project start '.$project->start_date->translatedFormat('j M'),
                null,
                $project,
                ActionSource::Automation,
            );

            $count++;
        }

        return $count;
    }
}
