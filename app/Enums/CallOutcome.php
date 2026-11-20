<?php

namespace App\Enums;

/**
 * Hoe een belpoging afliep. Elke uitkomst wordt een contactmoment in het
 * dossier en zet de volgende stap klaar.
 */
enum CallOutcome: string
{
    case Gesproken = 'gesproken';
    case GeenGehoor = 'geen_gehoor';
    case Voicemail = 'voicemail';
    case Terugbelverzoek = 'terugbelverzoek';

    public function label(): string
    {
        return match ($this) {
            self::Gesproken => 'Gesproken',
            self::GeenGehoor => 'Geen gehoor',
            self::Voicemail => 'Voicemail ingesproken',
            self::Terugbelverzoek => 'Terugbelverzoek',
        };
    }

    /** Telt deze poging als niet bereikt? Twee op rij en Nova raadt een ander kanaal aan. */
    public function isMissed(): bool
    {
        return in_array($this, [self::GeenGehoor, self::Voicemail], true);
    }
}
