<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Voorbereiding = 'voorbereiding';
    case Gepland = 'gepland';
    case Uitvoering = 'uitvoering';
    case Oplevering = 'oplevering';
    case Afgerond = 'afgerond';

    public function label(): string
    {
        return match ($this) {
            self::Voorbereiding => 'Voorbereiding',
            self::Gepland => 'Gepland',
            self::Uitvoering => 'Uitvoering',
            self::Oplevering => 'Oplevering',
            self::Afgerond => 'Afgerond',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Voorbereiding => 'bg-gray-200 text-gray-700',
            self::Gepland => 'bg-steel-100 text-steel-800',
            self::Uitvoering => 'bg-brand-100 text-brand-800',
            self::Oplevering => 'bg-amber-100 text-amber-800',
            self::Afgerond => 'bg-green-100 text-green-800',
        };
    }

    public function isActive(): bool
    {
        return $this !== self::Afgerond;
    }
}
