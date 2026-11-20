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
            self::Arbeid => 'bg-brand-500/15 text-brand-300',
            self::Materiaal => 'bg-ink/10 text-ink',
            self::Onderaannemer => 'bg-amber-500/15 text-amber-300',
            self::Materieel => 'bg-ink/10 text-ink2',
            self::Stelpost => 'bg-calc-500/15 text-calc-300',
        };
    }
}
