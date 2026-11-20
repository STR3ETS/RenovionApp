<?php

namespace App\Enums;

/**
 * Voor wie we werken. Een particulier laat een klus doen; een zakelijke klant
 * is een partij voor wie we werk uitvoeren, vaak onder een raamovereenkomst.
 */
enum CustomerType: string
{
    case Particulier = 'particulier';
    case Zakelijk = 'zakelijk';

    public function label(): string
    {
        return match ($this) {
            self::Particulier => 'Particulier',
            self::Zakelijk => 'Zakelijk',
        };
    }

    /** De betaaltermijn waarmee een nieuwe klant van dit soort begint. */
    public function defaultPaymentDays(): int
    {
        return match ($this) {
            self::Particulier => 14,
            self::Zakelijk => 30,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Particulier => 'bg-klant-500/15 text-klant-300',
            self::Zakelijk => 'bg-offerte-500/15 text-offerte-300',
        };
    }
}
