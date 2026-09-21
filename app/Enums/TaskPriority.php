<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Laag = 'laag';
    case Normaal = 'normaal';
    case Hoog = 'hoog';

    public function label(): string
    {
        return match ($this) {
            self::Laag => 'Laag',
            self::Normaal => 'Normaal',
            self::Hoog => 'Hoog',
        };
    }

    public function dotClasses(): string
    {
        return match ($this) {
            self::Laag => 'bg-gray-400',
            self::Normaal => 'bg-steel-500',
            self::Hoog => 'bg-red-500',
        };
    }
}
