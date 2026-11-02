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
            self::TeDoen => 'bg-white/10 text-slate-300',
            self::Bezig => 'bg-brand-500/15 text-brand-300',
            self::WachtOp => 'bg-amber-500/15 text-amber-300',
            self::Afgerond => 'bg-green-500/15 text-green-300',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Afgerond;
    }
}
