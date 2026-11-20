<?php

namespace App\Enums;

enum ScheduleEntryType: string
{
    case Project = 'project';
    case Afspraak = 'afspraak';
    case Terugbel = 'terugbel';
    case Intake = 'intake';
    case Overig = 'overig';

    public function label(): string
    {
        return match ($this) {
            self::Project => 'Projectwerk',
            self::Afspraak => 'Afspraak',
            self::Terugbel => 'Terugbelafspraak',
            self::Intake => 'Intake',
            self::Overig => 'Overig',
        };
    }

    public function blockClasses(): string
    {
        return match ($this) {
            self::Project => 'bg-brand-500/15 text-brand-300 border-brand-300',
            self::Afspraak => 'bg-offerte-500/15 text-offerte-300 border-offerte-300',
            self::Terugbel => 'bg-amber-500/15 text-amber-300 border-amber-300',
            self::Intake => 'bg-calc-500/15 text-calc-300 border-calc-300',
            self::Overig => 'bg-ink/10 text-ink border-surface-600',
        };
    }
}
