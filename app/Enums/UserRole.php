<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Sales = 'sales';
    case Projectleider = 'projectleider';
    case Werkvoorbereider = 'werkvoorbereider';
    case Uitvoerder = 'uitvoerder';
    case Klant = 'klant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Sales => 'Sales',
            self::Projectleider => 'Projectleider',
            self::Werkvoorbereider => 'Werkvoorbereider',
            self::Uitvoerder => 'Uitvoerder',
            self::Klant => 'Klant',
        };
    }

    /**
     * Interne rollen (alles behalve de portaalrol Klant), voor teambeheer.
     *
     * @return list<self>
     */
    public static function internal(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role !== self::Klant));
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Admin => 'bg-white/10 text-slate-200',
            self::Sales => 'bg-steel-100 text-steel-800',
            self::Projectleider => 'bg-brand-500/15 text-brand-300',
            self::Werkvoorbereider => 'bg-amber-500/15 text-amber-300',
            self::Uitvoerder => 'bg-white/10 text-slate-300',
            self::Klant => 'bg-green-500/15 text-green-300',
        };
    }
}
