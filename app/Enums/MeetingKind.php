<?php

namespace App\Enums;

enum MeetingKind: string
{
    case Intern = 'intern';
    case Bouwvergadering = 'bouwvergadering';
    case Klantgesprek = 'klantgesprek';
    case Intake = 'intake';
    case Oplevering = 'oplevering';

    public function label(): string
    {
        return match ($this) {
            self::Intern => 'Intern overleg',
            self::Bouwvergadering => 'Bouwvergadering',
            self::Klantgesprek => 'Gesprek met klant',
            self::Intake => 'Opname of intake',
            self::Oplevering => 'Oplevering',
        };
    }
}
