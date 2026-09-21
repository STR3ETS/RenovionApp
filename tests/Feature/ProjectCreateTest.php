<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_can_be_created_for_an_existing_customer(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $vakman = User::factory()->vakman()->create();

        $response = $this->actingAs($user)->post('/projecten', [
            'name' => 'Badkamer handmatig',
            'customer_id' => $customer->id,
            'status' => 'gepland',
            'value' => 12500,
            'deposit_amount' => 3750,
            'start_date' => today()->addDays(7)->toDateString(),
            'end_date_expected' => today()->addDays(30)->toDateString(),
            'project_leader_id' => $user->id,
            'craftsmen' => [$vakman->id],
        ]);

        $this->assertDatabaseHas('projects', [
            'name' => 'Badkamer handmatig',
            'customer_id' => $customer->id,
            'status' => 'gepland',
            'project_leader_id' => $user->id,
        ]);

        $project = Project::firstWhere('name', 'Badkamer handmatig');
        $response->assertRedirect(route('projects.show', $project));

        $this->assertTrue($project->craftsmen()->whereKey($vakman->id)->exists());
        $this->assertDatabaseHas('timeline_events', ['customer_id' => $customer->id, 'type' => 'projectupdate']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'project', 'action' => 'aangemaakt']);
    }

    public function test_project_can_be_created_with_a_new_customer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projecten', [
            'name' => 'Stucwerk Nieuw',
            'customer_name' => 'Familie Nieuw',
            'customer_phone' => '06-99887766',
            'customer_city' => 'Arnhem',
        ]);

        $this->assertDatabaseHas('customers', ['name' => 'Familie Nieuw', 'city' => 'Arnhem']);
        $this->assertDatabaseHas('projects', ['name' => 'Stucwerk Nieuw', 'city' => 'Arnhem', 'status' => 'voorbereiding']);
    }

    public function test_project_requires_a_name_and_a_customer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/projecten', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'customer_name']);
    }

    public function test_vakman_cannot_create_projects(): void
    {
        $vakman = User::factory()->vakman()->create();

        $this->actingAs($vakman)->get('/projecten/aanmaken')->assertForbidden();
        $this->actingAs($vakman)->post('/projecten', ['name' => 'X', 'customer_name' => 'Y'])->assertForbidden();
    }
}
