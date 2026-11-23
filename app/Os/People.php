<?php

namespace App\Os;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Medewerkers en extra gebruikers in de staat (emps, extraUsers) zijn de
 * mensen van Renovion OS; een inlogaccount (users) hoort bij elk van hen met
 * een e-mailadres. Deze klasse houdt die twee gelijk en bewaakt dat alleen
 * superadmin en directie rollen, status en e-mailadressen wijzigen.
 */
final class People
{
    /** Velden die bepalen wie waar bij kan. */
    private const SENSITIVE = ['sysrol', 'status', 'mail', 'scope'];

    public static function isPersonPath(string $path): bool
    {
        return str_starts_with($path, 'emps/') || str_starts_with($path, 'extraUsers/');
    }

    /** Een partnerbedrijf (subs) logt in op het partnerportaal met het e-mailadres van het bedrijf. */
    public static function isPartnerPath(string $path): bool
    {
        return str_starts_with($path, 'subs/') && ! str_ends_with($path, '/#order');
    }

    /**
     * Wie geen directie is, kan van een bestaande persoon de toegang niet
     * veranderen (rol, status, e-mail, projectrechten) en kan niemand
     * superadmin of directie maken. Die velden houden dan hun oude waarde.
     */
    public function guard(User $user, string $path, ?string $old, ?string $new): ?string
    {
        if (! self::isPersonPath($path) || $user->role->isManagement() || str_ends_with($path, '/#order')) {
            return $new;
        }

        $oldPerson = $old !== null ? json_decode($old, true) : null;

        if ($new === null) {
            // Alleen de directie haalt mensen weg.
            return $old;
        }

        $person = json_decode($new, true);
        if (! is_array($person)) {
            return $old;
        }

        if (is_array($oldPerson)) {
            foreach (self::SENSITIVE as $field) {
                if (array_key_exists($field, $oldPerson)) {
                    $person[$field] = $oldPerson[$field];
                } else {
                    unset($person[$field]);
                }
            }
        } elseif (in_array($person['sysrol'] ?? null, ['Superadmin', 'Directie'], true)) {
            $person['sysrol'] = 'Uitvoerder';
        }

        return json_encode($person, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Na het opslaan: accounts bijwerken voor de mensen die veranderden.
     * Nieuwe accounts krijgen een wachtwoord dat één keer terugkomt, zodat
     * de directie het kan doorgeven (er gaat geen mail de deur uit).
     *
     * @param  array<string, array{0: ?string, 1: ?string}>  $accepted
     * @return list<array{naam: string, mail: string, wachtwoord: string}>
     */
    public function reconcile(User $actor, array $accepted): array
    {
        $created = [];

        foreach ($accepted as $path => [, $value]) {
            $partner = self::isPartnerPath($path);
            if ((! self::isPersonPath($path) && ! $partner) || str_ends_with($path, '/#order')) {
                continue;
            }

            $personId = Layout::keyOf($path);
            $person = $value !== null ? json_decode($value, true) : null;
            if ($partner && is_array($person)) {
                // Een partner heeft geen rol of status in de staat: altijd onderaannemer, actief zolang het bedrijf bestaat.
                $person = ['naam' => $person['naam'] ?? ($person['contact'] ?? ''), 'mail' => $person['mail'] ?? '', 'sysrol' => 'Onderaannemer', 'status' => 'actief'];
                if (! empty($value) && ($sub = json_decode($value, true)) && ! empty($sub['empId'])) {
                    continue; // gekoppeld aan een medewerker van het type partner: die heeft het account
                }
            }

            if (! is_array($person)) {
                // Persoon weggehaald: het account gaat dicht, het werk blijft staan.
                User::query()->where('person_id', $personId)->update(['active' => false]);
                $this->endSessions(User::query()->where('person_id', $personId)->pluck('id')->all());

                continue;
            }

            $mail = Str::lower(trim((string) ($person['mail'] ?? '')));
            $account = User::query()->where('person_id', $personId)->first()
                ?? ($mail !== '' ? User::query()->whereRaw('lower(email) = ?', [$mail])->first() : null);

            $active = ($person['status'] ?? 'actief') !== 'gedeactiveerd';
            $role = UserRole::fromLabel($person['sysrol'] ?? null);

            if (! $account) {
                if ($mail === '' || ! filter_var($mail, FILTER_VALIDATE_EMAIL) || ! $active) {
                    continue;
                }

                $password = self::newPassword();
                User::query()->create([
                    'name' => (string) ($person['naam'] ?? $mail),
                    'email' => $mail,
                    'password' => $password,
                    'must_change_password' => true,
                    'role' => $role,
                    'active' => true,
                    'person_id' => $personId,
                ]);
                $created[] = ['naam' => (string) ($person['naam'] ?? $mail), 'mail' => $mail, 'wachtwoord' => $password];

                continue;
            }

            $changes = [
                'name' => (string) ($person['naam'] ?? $account->name),
                'role' => $role,
                'active' => $active,
                'person_id' => $personId,
            ];
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL) && $mail !== Str::lower($account->email)
                && ! User::query()->whereRaw('lower(email) = ?', [$mail])->exists()) {
                $changes['email'] = $mail;
            }
            $wasActive = $account->active;
            $account->update($changes);

            if ($wasActive && ! $active) {
                $this->endSessions([$account->id]);
            }
        }

        return $created;
    }

    /**
     * Zet een nieuw tijdelijk wachtwoord: de gebruiker kiest bij de volgende
     * keer inloggen zelf een eigen wachtwoord, open sessies gaan dicht.
     */
    public static function temporaryPassword(User $account): string
    {
        $password = self::newPassword();
        $account->forceFill(['password' => $password, 'must_change_password' => true, 'active' => true])->save();
        (new self)->endSessions([$account->id]);

        return $password;
    }

    public static function newPassword(): string
    {
        // Leesbaar door te geven: geen tekens die op elkaar lijken.
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    /** @param  list<int>  $userIds */
    private function endSessions(array $userIds): void
    {
        if ($userIds && config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->whereIn('user_id', $userIds)->delete();
        }
    }
}
