<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Veilig voor `php artisan migrate --seed` op de server: daar worden
     * alleen de vier teamaccounts aangemaakt. Demo-data komt er uitsluitend
     * bij op dev-omgevingen (whitelist: local/testing). De demo gebruikt geen
     * factories of Faker, dus hij werkt ook na `composer install --no-dev`.
     */
    public function run(): void
    {
        $this->call(TeamSeeder::class);
        $this->call(PriceLibrarySeeder::class);
        $this->call(QuoteTemplateSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
