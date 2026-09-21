<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Nieuw = 'nieuw';
    case Contact = 'contact';
    case Intake = 'intake';
    case Offerte = 'offerte';
    case Akkoord = 'akkoord';
    case Project = 'project';
    case Verloren = 'verloren';
    case OnHold = 'on_hold';

    /**
     * De pipeline-kolommen voor de Kanban-weergave, in volgorde.
     *
     * @return array<int, self>
     */
    public static function pipeline(): array
    {
        return [
            self::Nieuw,
            self::Contact,
            self::Intake,
            self::Offerte,
            self::Akkoord,
            self::Project,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Nieuw => 'Nieuw',
            self::Contact => 'Contact',
            self::Intake => 'Intake',
            self::Offerte => 'Offerte',
            self::Akkoord => 'Akkoord',
            self::Project => 'Project',
            self::Verloren => 'Verloren',
            self::OnHold => 'On hold',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Nieuw => 'bg-brand-100 text-brand-800',
            self::Contact => 'bg-steel-100 text-steel-800',
            self::Intake => 'bg-steel-100 text-steel-800',
            self::Offerte => 'bg-amber-100 text-amber-800',
            self::Akkoord => 'bg-green-100 text-green-800',
            self::Project => 'bg-navy-100 text-navy-800',
            self::Verloren => 'bg-red-100 text-red-800',
            self::OnHold => 'bg-gray-200 text-gray-700',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Project, self::Verloren], true);
    }
}
