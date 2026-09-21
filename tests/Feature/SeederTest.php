<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_on_production_creates_only_the_team_accounts(): void
    {
        $this->app['env'] = 'production';

        // Op productie vraagt db:seed om bevestiging; --force slaat die over (net als op live).
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('projects', 0);

        $imad = User::firstWhere('email', 'imad@renovion.nl');
        $this->assertSame(UserRole::Admin, $imad->role);
        $this->assertTrue(Hash::check('.ydX7Q.,Ck#2_Iwf', $imad->password));

        $peter = User::firstWhere('email', 'peter@renovion.nl');
        $this->assertSame(UserRole::Vakman, $peter->role);
    }

    public function test_seeding_locally_adds_demo_data_on_top_of_the_team(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 4);
        $this->assertTrue(Customer::count() > 0);
        $this->assertDatabaseHas('customers', ['name' => 'Familie Jansen']);
        $this->assertDatabaseHas('projects', ['name' => 'Complete renovatie Bakker']);
    }

    public function test_reseeding_never_overwrites_an_existing_account(): void
    {
        $this->seed(TeamSeeder::class);

        $raphael = User::firstWhere('email', 'raphael@renovion.nl');
        $raphael->update(['password' => 'zelf-gekozen-wachtwoord']);

        $this->seed(TeamSeeder::class);

        $this->assertDatabaseCount('users', 4);
        $this->assertTrue(Hash::check('zelf-gekozen-wachtwoord', $raphael->refresh()->password));
    }
}
