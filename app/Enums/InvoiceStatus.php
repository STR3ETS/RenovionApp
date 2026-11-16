<?php

namespace App\Enums;

/**
 * De staat van een uitgaande factuur. Een concept telt nergens mee; vanaf
 * verzonden staat hij in de debiteuren en op de cashflow.
 */
enum InvoiceStatus: string
{
    case Concept = 'concept';
    case Verzonden = 'verzonden';
    case Betaald = 'betaald';
    case Oninbaar = 'oninbaar';

    public function label(): string
    {
        return match ($this) {
            self::Concept => 'Concept',
            self::Verzonden => 'Verzonden',
            self::Betaald => 'Betaald',
            self::Oninbaar => 'Oninbaar',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Concept => 'bg-white/10 text-slate-300',
            self::Verzonden => 'bg-lead-500/15 text-lead-300',
            self::Betaald => 'bg-green-500/15 text-green-300',
            self::Oninbaar => 'bg-red-500/15 text-red-300',
        };
    }

    /** Lichte badges voor de app (api/app). */
    public function appBadgeClasses(): string
    {
        return match ($this) {
            self::Concept => 'bg-gray-200 text-gray-700',
            self::Verzonden => 'bg-steel-100 text-steel-800',
            self::Betaald => 'bg-green-100 text-green-800',
            self::Oninbaar => 'bg-red-100 text-red-800',
        };
    }

    /** Verzonden en nog niet betaald: dat staat open bij de klant. */
    public function isOutstanding(): bool
    {
        return $this === self::Verzonden;
    }

    /** Alles behalve een concept telt mee in de omzet. */
    public function countsAsRevenue(): bool
    {
        return $this !== self::Concept;
    }
}
