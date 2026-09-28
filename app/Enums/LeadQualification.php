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
            self::Onbeoordeeld => 'bg-gray-100 text-gray-500',
            self::Koud => 'bg-gray-200 text-gray-700',
            self::Warm => 'bg-amber-100 text-amber-800',
            self::Heet => 'bg-green-100 text-green-800',
        };
    }
}
