<?php

namespace App\Automations;

use App\Enums\ActionSource;
use App\Enums\LeadStatus;
use App\Enums\TaskPriority;
use App\Models\AutomationRun;
use App\Models\Lead;
use App\Models\Task;

/**
 * Briefing §22: nieuwe lead met complete gegevens → salestaak om op te volgen.
 */
class NewLeadTaskAutomation extends BaseAutomation
{
    public function key(): string
    {
        return 'nieuwe-lead-salestaak';
    }

    public function name(): string
    {
        return 'Salestaak bij nieuwe aanvraag';
    }

    public function description(): string
    {
        return 'Nieuwe lead + gegevens compleet (telefoonnummer bekend) → opvolgtaak voor sales.';
    }

    public function run(): int
    {
        $count = 0;

        $leads = Lead::where('status', LeadStatus::Nieuw)
            ->whereHas('customer', fn ($query) => $query->whereNotNull('phone'))
            ->with('customer')
            ->get();

        foreach ($leads as $lead) {
            if (! AutomationRun::claim($this->key(), $lead)) {
                continue;
            }

            $taak = [
                'title' => 'Nieuwe aanvraag opvolgen: '.$lead->customer->name,
                'note' => $lead->service,
                'customer_id' => $lead->customer_id,
                'lead_id' => $lead->id,
                'owner_id' => $lead->assigned_to,
                'deadline' => today()->addDay()->toDateString(),
                'priority' => TaskPriority::Normaal->value,
            ];

            $this->act(
                'Nieuwe aanvraag van '.$lead->customer->name.' wacht op opvolging.',
                fn () => Task::create([...$taak, 'priority' => TaskPriority::Normaal, 'source' => ActionSource::Automation]),
                ['type' => 'create_task', 'params' => $taak],
                route('leads.show', $lead),
            );

            $count++;
        }

        return $count;
    }
}
