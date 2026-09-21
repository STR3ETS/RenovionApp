<?php

namespace App\Automations;

/**
 * Automations volgen briefing §22: trigger → voorwaarden → actie.
 * Elke automation is idempotent via AutomationRun::claim().
 */
interface Automation
{
    public function key(): string;

    public function name(): string;

    /**
     * Trigger → voorwaarde → actie, zoals in de briefing-tabel.
     */
    public function description(): string;

    /**
     * Voer de automation uit en geef het aantal uitgevoerde acties terug.
     */
    public function run(): int;
}
