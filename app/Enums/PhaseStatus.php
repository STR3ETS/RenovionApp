<?php

namespace App\Enums;

enum PhaseStatus: string
{
    case NietGestart = 'niet_gestart';
    case Bezig = 'bezig';
    case WachtOpKlant = 'wacht_op_klant';
    case WachtOpLeverancier = 'wacht_op_leverancier';
    case Geblokkeerd = 'geblokkeerd';
    case Gereed = 'gereed';

    public function label(): string
    {
        return match ($this) {
            self::NietGestart => 'Niet gestart',
            self::Bezig => 'Bezig',
            self::WachtOpKlant => 'Wacht op klant',
            self::WachtOpLeverancier => 'Wacht op leverancier',
            self::Geblokkeerd => 'Geblokkeerd',
            self::Gereed => 'Gereed',
        };
    }

    /**
     * Statuskleuren uit de briefing: groen = op schema/gereed, oranje = aandacht
     * of wachten, rood = geblokkeerd, grijs = niet gestart.
     */
    public function dotColor(): string
    {
        return match ($this) {
            self::NietGestart => 'gray',
            self::Bezig, self::WachtOpKlant, self::WachtOpLeverancier => 'amber',
            self::Geblokkeerd => 'red',
            self::Gereed => 'green',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::NietGestart => 'bg-white/10 text-slate-400',
            self::Bezig => 'bg-brand-500/15 text-brand-300',
            self::WachtOpKlant, self::WachtOpLeverancier => 'bg-amber-500/15 text-amber-300',
            self::Geblokkeerd => 'bg-red-500/15 text-red-300',
            self::Gereed => 'bg-green-500/15 text-green-300',
        };
    }
}
