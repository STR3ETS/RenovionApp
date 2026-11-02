<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Veilig voor `php artisan migrate --seed` op de server: daar worden
     * alleen de vier teamaccounts aangemaakt. Demo-data komt er uitsluitend
     * bij op dev-omgevingen (whitelist: local/testing) - demo gebruikt
     * factories/Faker en die zijn met `composer install --no-dev` niet aanwezig.
     */
    public function run(): void
    {
        $this->call(TeamSeeder::class);
        $this->call(PriceLibrarySeeder::class);
        $this->call(QuoteTemplateSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoSeeder::class);
            // Demodata voor de modules uit briefing v3: begrotingen, klussen,
            // werkbonnen, geld, partners, kwaliteit, nazorg en de agents.
            $this->call(RenovionOsDemoSeeder::class);
        }
    }
}
