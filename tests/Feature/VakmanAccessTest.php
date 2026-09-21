<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VakmanAccessTest extends TestCase
{
    use RefreshDatabase;

    private function vakman(): User
    {
        return User::factory()->create(['role' => UserRole::Vakman, 'name' => 'Peter Vakman']);
    }

    public function test_vakman_cannot_reach_crm_pages(): void
    {
        $vakman = $this->vakman();

        $this->actingAs($vakman)->get('/aanvragen')->assertForbidden();
        $this->actingAs($vakman)->get('/klanten')->assertForbidden();
        $this->actingAs($vakman)->get('/offertes')->assertForbidden();
        $this->actingAs($vakman)->postJson('/nova', ['message' => 'test'])->assertForbidden();
    }

    public function test_vakman_sees_a_simplified_dashboard(): void
    {
        $vakman = $this->vakman();
        Task::factory()->create(['owner_id' => $vakman->id, 'title' => 'Stucwerk afronden']);

        $response = $this->actingAs($vakman)->get('/');

        $response->assertOk();
        $response->assertSee('Jouw taken');
        $response->assertSee('Stucwerk afronden');
        $response->assertDontSee('Nieuwe aanvragen');
    }

    public function test_vakman_sees_only_own_projects(): void
    {
        $vakman = $this->vakman();

        $own = Project::factory()->create(['name' => 'Eigen Badkamer']);
        $own->craftsmen()->attach($vakman);
        $other = Project::factory()->create(['name' => 'Andermans Keuken']);

        $response = $this->actingAs($vakman)->get('/projecten');

        $response->assertOk();
        $response->assertSee('Eigen Badkamer');
        $response->assertDontSee('Andermans Keuken');

        $this->actingAs($vakman)->get('/projecten/'.$own->id)->assertOk();
        $this->actingAs($vakman)->get('/projecten/'.$other->id)->assertForbidden();
    }

    public function test_vakman_sees_only_own_tasks_and_cannot_touch_others(): void
    {
        $vakman = $this->vakman();
        $collega = User::factory()->create();

        Task::factory()->create(['owner_id' => $vakman->id, 'title' => 'Eigen taak']);
        $andermans = Task::factory()->create(['owner_id' => $collega->id, 'title' => 'Andermans taak']);

        $response = $this->actingAs($vakman)->get('/taken');
        $response->assertOk();
        $response->assertSee('Eigen taak');
        $response->assertDontSee('Andermans taak');

        $this->actingAs($vakman)
            ->patchJson('/taken/'.$andermans->id.'/status', ['status' => 'afgerond'])
            ->assertForbidden();
    }

    public function test_admin_still_has_full_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach (['/aanvragen', '/klanten', '/offertes', '/aandacht', '/automations'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
