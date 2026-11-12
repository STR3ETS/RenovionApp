<?php

namespace App\Enums;

enum CalculationStatus: string
{
    case Concept = 'concept';
    case Definitief = 'definitief';

    public function label(): string
    {
        return match ($this) {
            self::Concept => 'Concept',
            self::Definitief => 'Definitief',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Concept => 'bg-white/10 text-slate-400',
            self::Definitief => 'bg-green-500/15 text-green-300',
        };
    }

    /** Lichte variant voor de schermen in de iOS/Android-app (api/app). */
    public function appBadgeClasses(): string
    {
        return match ($this) {
            self::Concept => 'bg-gray-200 text-gray-700',
            self::Definitief => 'bg-green-100 text-green-800',
        };
    }
}
