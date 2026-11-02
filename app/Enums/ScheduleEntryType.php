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
        // Categoriekleuren uit de huisstijl (oranje is voor acties, staalblauw wordt uitgefaseerd):
        // projectwerk = planning (roze), afspraak = klant (teal), intake = aanvraag (sky).
        return match ($this) {
            self::Project => 'bg-planning-100 text-planning-700 border-planning-200',
            self::Afspraak => 'bg-klant-100 text-klant-700 border-klant-200',
            self::Terugbel => 'bg-amber-100 text-amber-900 border-amber-300',
            self::Intake => 'bg-lead-100 text-lead-700 border-lead-200',
            self::Overig => 'bg-gray-100 text-gray-800 border-gray-300',
        };
    }
}
