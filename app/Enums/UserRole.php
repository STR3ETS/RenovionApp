<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Directie = 'directie';
    case Sales = 'sales';
    case Calculator = 'calculator';
    case Projectleider = 'projectleider';
    case Werkvoorbereider = 'werkvoorbereider';
    case Inkoper = 'inkoper';
    case Administratie = 'administratie';
    case Uitvoerder = 'uitvoerder';
    case Onderaannemer = 'onderaannemer';
    case Klant = 'klant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Superadmin',
            self::Directie => 'Directie',
            self::Sales => 'Sales',
            self::Calculator => 'Calculator',
            self::Projectleider => 'Projectleider',
            self::Werkvoorbereider => 'Werkvoorbereider',
            self::Inkoper => 'Inkoper',
            self::Administratie => 'Administratie',
            self::Uitvoerder => 'Uitvoerder',
            self::Onderaannemer => 'Onderaannemer',
            self::Klant => 'Klant',
        };
    }

    /**
     * Rollen die in de interne omgeving werken: alles behalve de twee portaalrollen.
     *
     * @return list<self>
     */
    public static function internal(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role) => ! in_array($role, [self::Klant, self::Onderaannemer], true),
        ));
    }

    /**
     * Rollen met een eigen buitenportaal in plaats van de interne omgeving.
     *
     * @return list<self>
     */
    public static function external(): array
    {
        return [self::Klant, self::Onderaannemer];
    }

    public function isExternal(): bool
    {
        return in_array($this, self::external(), true);
    }

    /**
     * Directie en superadmin zien alles en beheren de inrichting.
     */
    public function isManagement(): bool
    {
        return in_array($this, [self::Admin, self::Directie], true);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Admin, self::Directie => 'bg-white/10 text-slate-200',
            self::Sales => 'bg-lead-500/15 text-lead-300',
            self::Calculator => 'bg-calc-500/15 text-calc-300',
            self::Projectleider => 'bg-brand-500/15 text-brand-300',
            self::Werkvoorbereider => 'bg-amber-500/15 text-amber-300',
            self::Inkoper => 'bg-offerte-500/15 text-offerte-300',
            self::Administratie => 'bg-planning-500/15 text-planning-300',
            self::Uitvoerder => 'bg-white/10 text-slate-300',
            self::Onderaannemer => 'bg-klant-500/15 text-klant-300',
            self::Klant => 'bg-green-500/15 text-green-300',
        };
    }
}
