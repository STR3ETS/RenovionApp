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
            self::Admin => 'bg-navy-100 text-navy-800',
            self::Sales => 'bg-steel-100 text-steel-800',
            self::Projectleider => 'bg-brand-100 text-brand-800',
            self::Werkvoorbereider => 'bg-amber-100 text-amber-800',
            self::Uitvoerder => 'bg-gray-200 text-gray-700',
            self::Klant => 'bg-green-100 text-green-800',
        };
    }
}
