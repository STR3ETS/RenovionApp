<?php

namespace Database\Seeders;

use App\Os\StateStore;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * De teamaccounts en daarna de lege staat van Renovion Groep. Veilig op
     * productie: bestaande accounts en een bestaande staat blijven staan.
     */
    public function run(): void
    {
        $this->call(TeamSeeder::class);

        app(StateStore::class)->ensureInstalled();
    }
}
