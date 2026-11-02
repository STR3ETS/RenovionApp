<?php

namespace App\Enums;

/**
 * De status van een klus op de bouwplaats. De uitvoerder schuift hier zelf door:
 * gepland -> onderweg -> bezig (na de LMRA) -> eventueel pauze -> klaar.
 */
enum JobStatus: string
{
    case Gepland = 'gepland';
    case Onderweg = 'onderweg';
    case Bezig = 'bezig';
    case Pauze = 'pauze';
    case Klaar = 'klaar';

    public function label(): string
    {
        return match ($this) {
            self::Gepland => 'Gepland',
            self::Onderweg => 'Onderweg',
            self::Bezig => 'Bezig',
            self::Pauze => 'Pauze',
            self::Klaar => 'Afgerond',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Gepland => 'bg-white/10 text-slate-300',
            self::Onderweg => 'bg-lead-500/15 text-lead-300',
            self::Bezig => 'bg-brand-500/15 text-brand-300',
            self::Pauze => 'bg-amber-500/15 text-amber-300',
            self::Klaar => 'bg-green-500/15 text-green-300',
        };
    }

    public function dotColor(): string
    {
        return match ($this) {
            self::Gepland => 'gray',
            self::Onderweg => 'steel',
            self::Bezig => 'amber',
            self::Pauze => 'amber',
            self::Klaar => 'green',
        };
    }

    /** De kleurlijn bovenaan de kluskaart. */
    public function accentClasses(): string
    {
        return match ($this) {
            self::Gepland => 'bg-surface-600',
            self::Onderweg => 'bg-lead-500',
            self::Bezig => 'bg-brand-500',
            self::Pauze => 'bg-amber-500',
            self::Klaar => 'bg-green-500',
        };
    }

    /**
     * De grote knop onderaan het klusscherm: label, icoon en de volgende status.
     *
     * @return array{label: string, icon: string, next: self}|null
     */
    public function primaryAction(): ?array
    {
        return match ($this) {
            self::Gepland => ['label' => 'Ik ben onderweg', 'icon' => 'map-pin', 'next' => self::Onderweg],
            self::Onderweg => ['label' => 'Start werk', 'icon' => 'play', 'next' => self::Bezig],
            self::Bezig => ['label' => 'Pauze', 'icon' => 'pause', 'next' => self::Pauze],
            self::Pauze => ['label' => 'Hervat', 'icon' => 'play', 'next' => self::Bezig],
            self::Klaar => null,
        };
    }

    /** Vanaf hier mag de uitvoerder stappen afvinken en de klus afronden. */
    public function isActive(): bool
    {
        return in_array($this, [self::Bezig, self::Pauze], true);
    }
}
