<?php

namespace App\Automations;

use App\Enums\ActionSource;
use App\Enums\ScheduleEntryType;
use App\Enums\TaskPriority;
use App\Models\AutomationRun;
use App\Models\ScheduleEntry;
use App\Models\Task;

/**
 * Briefing §22: intake gepland → 24 uur vooraf een reminder voor de medewerker.
 */
class IntakeReminderAutomation implements Automation
{
    public function key(): string
    {
        return 'intake-reminder';
    }

    public function name(): string
    {
        return 'Intake-reminder';
    }

    public function description(): string
    {
        return 'Intake gepland → 24 uur vooraf een remindertaak voor de betreffende medewerker.';
    }

    public function run(): int
    {
        $count = 0;

        $entries = ScheduleEntry::where('type', ScheduleEntryType::Intake)
            ->whereDate('date', today()->addDay())
            ->with(['customer', 'user'])
            ->get();

        foreach ($entries as $entry) {
            if (! AutomationRun::claim($this->key(), $entry)) {
                continue;
            }

            Task::create([
                'title' => 'Herinnering: intake'.($entry->customer ? ' met '.$entry->customer->name : '').' morgen'.($entry->start_time ? ' om '.substr($entry->start_time, 0, 5) : ''),
                'customer_id' => $entry->customer_id,
                'owner_id' => $entry->user_id,
                'deadline' => $entry->date->toDateString(),
                'priority' => TaskPriority::Normaal,
                'source' => ActionSource::Automation,
            ]);

            $count++;
        }

        return $count;
    }
}
