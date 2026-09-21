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
            ['name' => 'Imad', 'email' => 'imad@renovion.nl', 'role' => UserRole::Admin, 'password' => '.ydX7Q.,Ck#2_Iwf'],
            ['name' => 'Raphael', 'email' => 'raphael@renovion.nl', 'role' => UserRole::Admin, 'password' => 'or|8<4hGIBB4JY9a'],
            ['name' => 'Peter', 'email' => 'peter@renovion.nl', 'role' => UserRole::Vakman, 'password' => 'ao&5V,hR#aDn;i2f'],
            ['name' => 'Mehmet', 'email' => 'mehmet@renovion.nl', 'role' => UserRole::Vakman, 'password' => ')m:g7qT6</p9YdcS'],
        ];

        foreach ($team as $member) {
            User::firstOrCreate(
                ['email' => $member['email']],
                ['name' => $member['name'], 'role' => $member['role'], 'password' => $member['password']],
            );
        }
    }
}
