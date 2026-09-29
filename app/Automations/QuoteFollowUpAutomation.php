<?php

namespace App\Automations;

use App\Enums\ActionSource;
use App\Enums\QuoteStatus;
use App\Enums\TaskPriority;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\AutomationRun;
use App\Models\Quote;
use App\Models\Task;

/**
 * Briefing §11/§22: offerte verstuurd en 3 dagen geen reactie → opvolgtaak
 * en status "Opvolgen". Vanaf 5 dagen krijgt de taak hoge prioriteit.
 */
class QuoteFollowUpAutomation extends BaseAutomation
{
    public function key(): string
    {
        return 'offerte-opvolging';
    }

    public function name(): string
    {
        return 'Offerte-opvolging';
    }

    public function description(): string
    {
        return 'Offerte verstuurd + 3 dagen geen reactie → opvolgtaak en status "Opvolgen" (5+ dagen = hoge prioriteit).';
    }

    public function run(): int
    {
        $count = 0;

        $quotes = Quote::whereIn('status', [QuoteStatus::Verstuurd, QuoteStatus::Bekeken])
            ->whereNotNull('sent_at')
            ->where('sent_at', '<=', now()->subDays(3))
            ->with(['customer', 'lead'])
            ->get();

        foreach ($quotes as $quote) {
            if (! AutomationRun::claim($this->key(), $quote)) {
                continue;
            }

            $taak = [
                'title' => 'Offerte '.$quote->number.' opvolgen ('.$quote->daysOpen().' dagen open)',
                'customer_id' => $quote->customer_id,
                'lead_id' => $quote->lead_id,
                'owner_id' => $quote->lead?->assigned_to,
                'deadline' => today()->toDateString(),
                'priority' => $quote->daysOpen() >= 5 ? TaskPriority::Hoog->value : TaskPriority::Normaal->value,
            ];

            $this->act(
                'Offerte '.$quote->number.' van '.$quote->customer->name.' staat '.$quote->daysOpen().' dagen open zonder reactie.',
                function () use ($quote, $taak) {
                    $old = $quote->status;
                    $quote->forceFill(['status' => QuoteStatus::Opvolgen])->save();

                    AuditLog::record($quote, 'status_gewijzigd', ['status' => $old->value], ['status' => QuoteStatus::Opvolgen->value], ActionSource::Automation);

                    Task::create([...$taak, 'priority' => TaskPriority::from($taak['priority']), 'source' => ActionSource::Automation]);

                    $quote->customer->recordEvent(
                        TimelineEventType::Offerte,
                        'Offerte '.$quote->number.' gemarkeerd voor opvolging ('.$quote->daysOpen().' dagen geen reactie)',
                        null,
                        $quote,
                        ActionSource::Automation,
                    );
                },
                ['type' => 'create_task', 'params' => $taak],
                route('quotes.show', $quote),
            );

            $count++;
        }

        return $count;
    }
}
