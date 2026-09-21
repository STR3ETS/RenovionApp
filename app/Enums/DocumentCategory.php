<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Tekening = 'tekening';
    case Foto = 'foto';
    case Offerte = 'offerte';
    case Calculatie = 'calculatie';
    case Factuur = 'factuur';
    case Contract = 'contract';
    case Technisch = 'technisch';
    case Oplevering = 'oplevering';
    case Overig = 'overig';

    public function label(): string
    {
        return match ($this) {
            self::Tekening => 'Tekening',
            self::Foto => 'Foto',
            self::Offerte => 'Offerte',
            self::Calculatie => 'Calculatie',
            self::Factuur => 'Factuur',
            self::Contract => 'Contract',
            self::Technisch => 'Technische documentatie',
            self::Oplevering => 'Opleverdocument',
            self::Overig => 'Overig',
        };
    }
}
