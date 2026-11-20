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
            self::Nieuw => 'bg-brand-500/15 text-brand-300',
            self::Contact => 'bg-offerte-500/15 text-offerte-300',
            self::Intake => 'bg-calc-500/15 text-calc-300',
            self::Offerte => 'bg-amber-500/15 text-amber-300',
            self::Akkoord => 'bg-green-500/15 text-green-300',
            self::Project => 'bg-ink/10 text-ink',
            self::Verloren => 'bg-red-500/15 text-red-300',
            self::OnHold => 'bg-ink/10 text-ink2',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Project, self::Verloren], true);
    }
}
