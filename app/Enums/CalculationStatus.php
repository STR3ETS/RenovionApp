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
            self::Concept => 'bg-ink/10 text-muted',
            self::Definitief => 'bg-green-500/15 text-green-300',
        };
    }
}
