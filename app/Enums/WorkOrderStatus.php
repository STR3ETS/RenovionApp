<?php

namespace App\Enums;

/**
 * De weg van een werkbon: werkvoorbereiding maakt hem, de uitvoerder vult hem op
 * de bouwplaats, de projectleider keurt hem goed. Pas na goedkeuring tellen de
 * uren mee in de nacalculatie en de voortgang.
 */
enum WorkOrderStatus: string
{
    case Concept = 'concept';
    case Gepland = 'gepland';
    case Uitgegeven = 'uitgegeven';
    case Uitvoering = 'uitvoering';
    case Controle = 'controle';
    case Goedgekeurd = 'goedgekeurd';
    case Afgesloten = 'afgesloten';

    public function label(): string
    {
        return match ($this) {
            self::Concept => 'Concept',
            self::Gepland => 'Gepland',
            self::Uitgegeven => 'Uitgegeven',
            self::Uitvoering => 'In uitvoering',
            self::Controle => 'Ter controle',
            self::Goedgekeurd => 'Goedgekeurd',
            self::Afgesloten => 'Afgesloten',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Concept, self::Afgesloten => 'bg-white/10 text-slate-300',
            self::Gepland => 'bg-lead-500/15 text-lead-300',
            self::Uitgegeven => 'bg-calc-500/15 text-calc-300',
            self::Uitvoering => 'bg-brand-500/15 text-brand-300',
            self::Controle => 'bg-amber-500/15 text-amber-300',
            self::Goedgekeurd => 'bg-green-500/15 text-green-300',
        };
    }

    /** Positie in de keten, om te vergelijken wat er al gebeurd is. */
    public function step(): int
    {
        return match ($this) {
            self::Concept => 0,
            self::Gepland => 1,
            self::Uitgegeven => 2,
            self::Uitvoering => 3,
            self::Controle => 4,
            self::Goedgekeurd => 5,
            self::Afgesloten => 6,
        };
    }

    /** Vanaf goedkeuring tellen de uren mee in kosten en voortgang. */
    public function countsTowardsCost(): bool
    {
        return $this->step() >= self::Goedgekeurd->step();
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Goedgekeurd, self::Afgesloten], true);
    }

    /**
     * Statussen die de projectleider in de lijst wil kunnen filteren.
     *
     * @return list<self>
     */
    public static function filters(): array
    {
        return [self::Controle, self::Uitvoering, self::Uitgegeven, self::Gepland, self::Goedgekeurd, self::Concept];
    }
}
