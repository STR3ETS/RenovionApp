<?php

namespace App\Enums;

/**
 * Soorten melding die een uitvoerder vanaf de bouwplaats kan maken. Een melding
 * komt bij de projectleider terecht en blijft open tot die hem afhandelt.
 */
enum JobReportType: string
{
    case Probleem = 'probleem';
    case Meerwerk = 'meerwerk';
    case Materiaal = 'materiaal';
    case Schade = 'schade';
    case Onveilig = 'onveilig';
    case Vraag = 'vraag';

    public function label(): string
    {
        return match ($this) {
            self::Probleem => 'Probleem of afwijking',
            self::Meerwerk => 'Klant vraagt meerwerk',
            self::Materiaal => 'Materiaal tekort of fout',
            self::Schade => 'Schade',
            self::Onveilig => 'Onveilige situatie',
            self::Vraag => 'Vraag aan uitvoerder',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Probleem => 'exclamation-triangle',
            self::Meerwerk => 'currency-euro',
            self::Materiaal => 'shopping-cart',
            self::Schade => 'wrench',
            self::Onveilig => 'flag',
            self::Vraag => 'chat-bubble',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Probleem, self::Schade, self::Onveilig => 'bg-red-500/15 text-red-300',
            self::Meerwerk => 'bg-calc-500/15 text-calc-300',
            self::Materiaal => 'bg-amber-500/15 text-amber-300',
            self::Vraag => 'bg-lead-500/15 text-lead-300',
        };
    }

    /** Een melding die het werk stillegt of geld kost, wil het MT direct zien. */
    public function isSevere(): bool
    {
        return in_array($this, [self::Schade, self::Onveilig, self::Probleem], true);
    }
}
