<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_customers_projects_and_tasks(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Familie Jansen', 'city' => 'Huissen']);
        Project::factory()->create(['name' => 'Badkamer Jansen', 'customer_id' => $customer->id]);
        Task::factory()->create(['title' => 'Jansen terugbellen']);
        Customer::factory()->create(['name' => 'Familie De Vries']);

        $response = $this->actingAs($user)->get('/zoeken?q=Jansen');

        $response->assertOk();
        $response->assertSee('Familie Jansen');
        $response->assertSee('Badkamer Jansen');
        $response->assertSee('Jansen terugbellen');
        $response->assertDontSee('Familie De Vries');
    }

    public function test_search_without_query_shows_a_hint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/zoeken')
            ->assertOk()
            ->assertSee('Typ een zoekterm');
    }

    public function test_search_matches_on_customer_name_for_projects(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Familie Bakker']);
        Project::factory()->create(['name' => 'Complete renovatie', 'customer_id' => $customer->id]);

        $this->actingAs($user)->get('/zoeken?q=Bakker')
            ->assertOk()
            ->assertSee('Complete renovatie');
    }
}
