<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Veilig voor `php artisan migrate --seed` op de server: daar worden
     * alleen de vier teamaccounts aangemaakt. Demo-data komt er uitsluitend
     * bij op dev-omgevingen (whitelist: local/testing) — demo gebruikt
     * factories/Faker en die zijn met `composer install --no-dev` niet aanwezig.
     */
    public function run(): void
    {
        $this->call(TeamSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
