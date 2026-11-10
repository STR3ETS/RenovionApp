<?php

namespace App\Enums;

/**
 * Stoplicht voor de projectcockpit (briefing hoofdstuk 7). Elk project in
 * uitvoering krijgt vier lichten (planning, budget, kwaliteit, klant); het
 * totaal is altijd het slechtste licht.
 */
enum HealthLight: string
{
    case Groen = 'groen';
    case Oranje = 'oranje';
    case Rood = 'rood';
    case Grijs = 'grijs';

    public function label(): string
    {
        return match ($this) {
            self::Groen => 'Op schema',
            self::Oranje => 'Aandacht',
            self::Rood => 'Actie nodig',
            self::Grijs => 'Nog niet in uitvoering',
        };
    }

    /**
     * Hoe ernstig dit licht is. Hoger is slechter, zodat het totaal het
     * slechtste licht van de vier kan zijn.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Grijs => 0,
            self::Groen => 1,
            self::Oranje => 2,
            self::Rood => 3,
        };
    }

    public function dotColor(): string
    {
        return match ($this) {
            self::Groen => 'green',
            self::Oranje => 'amber',
            self::Rood => 'red',
            self::Grijs => 'gray',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Groen => 'bg-green-500/15 text-green-300',
            self::Oranje => 'bg-amber-500/15 text-amber-300',
            self::Rood => 'bg-red-500/15 text-red-300',
            self::Grijs => 'bg-white/10 text-slate-400',
        };
    }

    public function cardClasses(): string
    {
        return match ($this) {
            self::Groen => 'border-green-500/30 bg-green-500/10',
            self::Oranje => 'border-amber-500/30 bg-amber-500/10',
            self::Rood => 'border-red-500/30 bg-red-500/10',
            self::Grijs => 'border-surface-700 bg-white/5',
        };
    }

    public function borderClasses(): string
    {
        return match ($this) {
            self::Groen => 'border-l-green-500',
            self::Oranje => 'border-l-amber-500',
            self::Rood => 'border-l-red-500',
            self::Grijs => 'border-l-slate-600',
        };
    }

    public function textClasses(): string
    {
        return match ($this) {
            self::Groen => 'text-green-300',
            self::Oranje => 'text-amber-300',
            self::Rood => 'text-red-300',
            self::Grijs => 'text-slate-400',
        };
    }

    /**
     * Het slechtste licht uit een reeks; zonder lichten is het grijs.
     */
    public static function worst(self ...$lights): self
    {
        $worst = self::Grijs;

        foreach ($lights as $light) {
            if ($light->severity() > $worst->severity()) {
                $worst = $light;
            }
        }

        return $worst;
    }
}
