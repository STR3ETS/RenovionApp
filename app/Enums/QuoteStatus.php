<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Concept = 'concept';
    case Verstuurd = 'verstuurd';
    case Bekeken = 'bekeken';
    case Opvolgen = 'opvolgen';
    case Akkoord = 'akkoord';
    case Afgewezen = 'afgewezen';

    public function label(): string
    {
        return match ($this) {
            self::Concept => 'Concept',
            self::Verstuurd => 'Verstuurd',
            self::Bekeken => 'Bekeken',
            self::Opvolgen => 'Opvolgen',
            self::Akkoord => 'Akkoord',
            self::Afgewezen => 'Afgewezen',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Concept => 'bg-gray-200 text-gray-700',
            self::Verstuurd => 'bg-steel-100 text-steel-800',
            self::Bekeken => 'bg-amber-100 text-amber-800',
            self::Opvolgen => 'bg-brand-100 text-brand-800',
            self::Akkoord => 'bg-green-100 text-green-800',
            self::Afgewezen => 'bg-red-100 text-red-800',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Akkoord, self::Afgewezen], true);
    }
}
