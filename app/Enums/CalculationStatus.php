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
            self::Concept => 'bg-gray-100 text-gray-600',
            self::Definitief => 'bg-green-100 text-green-800',
        };
    }
}
