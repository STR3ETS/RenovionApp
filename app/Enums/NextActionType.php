<?php

namespace App\Enums;

/**
 * De volgende stap op een aanvraag. Staat met een datum en tijd in de agenda
 * van wie hem moet doen.
 */
enum NextActionType: string
{
    case Bellen = 'bellen';
    case Terugbellen = 'terug';
    case Afspraak = 'afspraak';
    case Mail = 'mail';
    case Offerte = 'offerte';

    public function label(): string
    {
        return match ($this) {
            self::Bellen => 'Bellen',
            self::Terugbellen => 'Terugbellen',
            self::Afspraak => 'Afspraak maken',
            self::Mail => 'Mailen',
            self::Offerte => 'Offerte opvolgen',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Bellen, self::Terugbellen => 'phone',
            self::Afspraak => 'calendar',
            self::Mail => 'envelope',
            self::Offerte => 'document-text',
        };
    }

    /** Een stap die je met de telefoon doet: daar hoort de belpoging bij. */
    public function isCall(): bool
    {
        return in_array($this, [self::Bellen, self::Terugbellen], true);
    }
}
