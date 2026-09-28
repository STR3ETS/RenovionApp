<?php

namespace App\Enums;

enum CalculationLineType: string
{
    case Arbeid = 'arbeid';
    case Materiaal = 'materiaal';
    case Onderaannemer = 'onderaannemer';
    case Materieel = 'materieel';
    case Stelpost = 'stelpost';

    public function label(): string
    {
        return match ($this) {
            self::Arbeid => 'Arbeid',
            self::Materiaal => 'Materiaal',
            self::Onderaannemer => 'Onderaannemer',
            self::Materieel => 'Materieel',
            self::Stelpost => 'Stelpost',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Arbeid => 'bg-brand-100 text-brand-800',
            self::Materiaal => 'bg-navy-100 text-navy-800',
            self::Onderaannemer => 'bg-amber-100 text-amber-800',
            self::Materieel => 'bg-gray-200 text-gray-700',
            self::Stelpost => 'bg-steel-100 text-steel-800',
        };
    }
}
