<?php

namespace App\Enums;

/**
 * De elf rollen van Renovion Groep. De waarde staat in de database (users.role),
 * het label is de naam die het systeem zelf gebruikt (sysrol in de staat).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Directie = 'directie';
    case Sales = 'sales';
    case Calculator = 'calculator';
    case Projectleider = 'projectleider';
    case Werkvoorbereider = 'werkvoorbereider';
    case Inkoper = 'inkoper';
    case Administratie = 'administratie';
    case Uitvoerder = 'uitvoerder';
    case Onderaannemer = 'onderaannemer';
    case Klant = 'klant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Superadmin',
            default => ucfirst($this->value),
        };
    }

    /** De rol bij een label uit het systeem; onbekend wordt de kleinste interne rol. */
    public static function fromLabel(?string $label): self
    {
        foreach (self::cases() as $role) {
            if ($role->label() === $label) {
                return $role;
            }
        }

        return self::Uitvoerder;
    }

    /** Klant en onderaannemer zien alleen hun eigen portaal en hun eigen gegevens. */
    public function isExternal(): bool
    {
        return in_array($this, [self::Klant, self::Onderaannemer], true);
    }

    /** Superadmin en directie richten het systeem in (team, instellingen, automations). */
    public function isManagement(): bool
    {
        return in_array($this, [self::Admin, self::Directie], true);
    }
}
