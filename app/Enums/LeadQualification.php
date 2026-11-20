<?php

namespace App\Enums;

enum LeadQualification: string
{
    case Onbeoordeeld = 'onbeoordeeld';
    case Koud = 'koud';
    case Warm = 'warm';
    case Heet = 'heet';

    public function label(): string
    {
        return match ($this) {
            self::Onbeoordeeld => 'Nog niet gekwalificeerd',
            self::Koud => 'Koud',
            self::Warm => 'Warm',
            self::Heet => 'Heet',
        };
    }

    /**
     * Signaalkleur voor de x-signal-dot component.
     */
    public function dotColor(): string
    {
        return match ($this) {
            self::Onbeoordeeld, self::Koud => 'gray',
            self::Warm => 'amber',
            self::Heet => 'green',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Onbeoordeeld => 'bg-ink/10 text-muted',
            self::Koud => 'bg-ink/10 text-ink2',
            self::Warm => 'bg-amber-500/15 text-amber-300',
            self::Heet => 'bg-green-500/15 text-green-300',
        };
    }
}
