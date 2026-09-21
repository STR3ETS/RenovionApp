<?php

namespace App\Enums;

enum TaskStatus: string
{
    case TeDoen = 'te_doen';
    case Bezig = 'bezig';
    case WachtOp = 'wacht_op';
    case Afgerond = 'afgerond';

    public function label(): string
    {
        return match ($this) {
            self::TeDoen => 'Te doen',
            self::Bezig => 'Bezig',
            self::WachtOp => 'Wacht op',
            self::Afgerond => 'Afgerond',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::TeDoen => 'bg-gray-200 text-gray-700',
            self::Bezig => 'bg-brand-100 text-brand-800',
            self::WachtOp => 'bg-amber-100 text-amber-800',
            self::Afgerond => 'bg-green-100 text-green-800',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Afgerond;
    }
}
