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
            self::Groen => 'bg-green-100 text-green-800',
            self::Oranje => 'bg-amber-100 text-amber-800',
            self::Rood => 'bg-red-100 text-red-800',
            self::Grijs => 'bg-gray-100 text-gray-600',
        };
    }

    public function cardClasses(): string
    {
        return match ($this) {
            self::Groen => 'border-green-200 bg-green-50',
            self::Oranje => 'border-amber-200 bg-amber-50',
            self::Rood => 'border-red-200 bg-red-50',
            self::Grijs => 'border-gray-200 bg-gray-50',
        };
    }

    public function borderClasses(): string
    {
        return match ($this) {
            self::Groen => 'border-l-green-500',
            self::Oranje => 'border-l-amber-500',
            self::Rood => 'border-l-red-500',
            self::Grijs => 'border-l-gray-300',
        };
    }

    public function textClasses(): string
    {
        return match ($this) {
            self::Groen => 'text-green-700',
            self::Oranje => 'text-amber-700',
            self::Rood => 'text-red-700',
            self::Grijs => 'text-gray-500',
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
