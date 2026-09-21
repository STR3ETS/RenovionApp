<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Veilig voor `php artisan migrate --seed` op productie: daar worden
     * alleen de vier teamaccounts aangemaakt. Demo-data komt er uitsluitend
     * bij op niet-productieomgevingen (lokaal ontwikkelen).
     */
    public function run(): void
    {
        $this->call(TeamSeeder::class);

        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
