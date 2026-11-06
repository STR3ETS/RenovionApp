<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * De vier Renovion-teamaccounts. Veilig voor productie en idempotent:
 * bestaande accounts (op e-mailadres) worden nooit overschreven, dus een
 * later gewijzigd wachtwoord blijft staan bij opnieuw seeden.
 */
class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $team = [
            ['name' => 'Imad', 'email' => 'imad@renovion.nl', 'role' => UserRole::Admin, 'password' => 'renoviontest26'],
            ['name' => 'Raphael', 'email' => 'raphael@renovion.nl', 'role' => UserRole::Admin, 'password' => 'renoviontest26'],
            ['name' => 'Peter', 'email' => 'peter@renovion.nl', 'role' => UserRole::Uitvoerder, 'password' => 'renoviontest26'],
            ['name' => 'Mehmet', 'email' => 'mehmet@renovion.nl', 'role' => UserRole::Uitvoerder, 'password' => 'renoviontest26'],
        ];

        foreach ($team as $member) {
            User::firstOrCreate(
                ['email' => $member['email']],
                ['name' => $member['name'], 'role' => $member['role'], 'password' => $member['password']],
            );
        }
    }
}
