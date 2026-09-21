<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Sales = 'sales';
    case Projectleider = 'projectleider';
    case Vakman = 'vakman';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Sales => 'Sales',
            self::Projectleider => 'Projectleider',
            self::Vakman => 'Vakman',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Admin => 'bg-navy-100 text-navy-800',
            self::Sales => 'bg-steel-100 text-steel-800',
            self::Projectleider => 'bg-brand-100 text-brand-800',
            self::Vakman => 'bg-gray-200 text-gray-700',
        };
    }
}
