<?php

namespace App\Enums;

/**
 * Soorten uitgaande factuur. Een termijnfactuur hangt aan een termijn uit de
 * begroting; de eindafrekening verrekent meer- en minderwerk en sluit het
 * project financieel af.
 */
enum InvoiceType: string
{
    case Termijn = 'termijn';
    case Meerwerk = 'meerwerk';
    case Eindafrekening = 'eindafrekening';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Termijn => 'Termijnfactuur',
            self::Meerwerk => 'Meerwerkfactuur',
            self::Eindafrekening => 'Eindafrekening',
            self::Credit => 'Creditnota',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Termijn => 'bg-lead-500/15 text-lead-300',
            self::Meerwerk => 'bg-calc-500/15 text-calc-300',
            self::Eindafrekening => 'bg-project-500/15 text-project-300',
            self::Credit => 'bg-amber-500/15 text-amber-300',
        };
    }

    /** Een creditnota gaat van de omzet af in plaats van erbij. */
    public function sign(): int
    {
        return $this === self::Credit ? -1 : 1;
    }
}
