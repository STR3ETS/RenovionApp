<?php

namespace App\Automations;

use App\Enums\AutomationMode;

/**
 * Automations volgen briefing §22: trigger → voorwaarden → actie.
 * Elke automation is idempotent via AutomationRun::claim().
 */
interface Automation
{
    public function key(): string;

    public function name(): string;

    /**
     * Ingestelde modus (briefing §12): uit / signaleren / bevestigen / automatisch.
     */
    public function mode(): AutomationMode;

    public function defaultMode(): AutomationMode;

    /**
     * Trigger → voorwaarde → actie, zoals in de briefing-tabel.
     */
    public function description(): string;

    /**
     * Voer de automation uit en geef het aantal uitgevoerde acties terug.
     */
    public function run(): int;
}
