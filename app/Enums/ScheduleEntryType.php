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
            self::Project => 'bg-brand-500/15 text-brand-900 border-brand-300',
            self::Afspraak => 'bg-steel-100 text-steel-900 border-steel-300',
            self::Terugbel => 'bg-amber-500/15 text-amber-900 border-amber-300',
            self::Intake => 'bg-purple-500/15 text-purple-900 border-purple-300',
            self::Overig => 'bg-white/10 text-slate-200 border-surface-600',
        };
    }
}
