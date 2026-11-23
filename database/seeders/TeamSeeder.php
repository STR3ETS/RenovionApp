<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Os\People;
use Illuminate\Database\Seeder;

/**
 * De vier Renovion-teamaccounts. Veilig voor productie en idempotent:
 * bestaande accounts (op e-mailadres) worden nooit overschreven. Nieuwe
 * accounts krijgen een tijdelijk wachtwoord dat één keer in beeld komt; bij
 * de eerste keer inloggen kiest de gebruiker zelf een eigen wachtwoord.
 * Er staan bewust geen wachtwoorden in de code.
 */
class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $team = [
            ['name' => 'Imad', 'email' => 'imad@renovion.nl', 'role' => UserRole::Admin],
            ['name' => 'Raphael', 'email' => 'raphael@renovion.nl', 'role' => UserRole::Admin],
            ['name' => 'Peter', 'email' => 'peter@renovion.nl', 'role' => UserRole::Uitvoerder],
            ['name' => 'Mehmet', 'email' => 'mehmet@renovion.nl', 'role' => UserRole::Uitvoerder],
        ];

        foreach ($team as $member) {
            if (User::query()->where('email', $member['email'])->exists()) {
                continue;
            }

            $password = People::newPassword();
            User::query()->create([...$member, 'password' => $password, 'must_change_password' => true]);
            $this->command?->line("{$member['email']}  tijdelijk wachtwoord: {$password}");
        }
    }
}
