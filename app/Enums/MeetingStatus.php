<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Opname = 'opname';
    case Verwerken = 'verwerken';
    case Klaar = 'klaar';
    case Mislukt = 'mislukt';

    public function label(): string
    {
        return match ($this) {
            self::Opname => 'Opname loopt',
            self::Verwerken => 'Nova schrijft het verslag',
            self::Klaar => 'Klaar',
            self::Mislukt => 'Mislukt',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Opname => 'bg-red-100 text-red-700',
            self::Verwerken => 'bg-amber-100 text-amber-800',
            self::Klaar => 'bg-green-100 text-green-800',
            self::Mislukt => 'bg-red-100 text-red-700',
        };
    }
}
