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
            self::Voorbereiding => 'bg-white/10 text-slate-300',
            self::Gepland => 'bg-steel-100 text-steel-800',
            self::Uitvoering => 'bg-brand-500/15 text-brand-300',
            self::Oplevering => 'bg-amber-500/15 text-amber-300',
            self::Afgerond => 'bg-green-500/15 text-green-300',
        };
    }

    public function isActive(): bool
    {
        return $this !== self::Afgerond;
    }
}
