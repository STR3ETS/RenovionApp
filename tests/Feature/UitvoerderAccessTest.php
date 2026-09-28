<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UitvoerderAccessTest extends TestCase
{
    use RefreshDatabase;

    private function uitvoerder(): User
    {
        return User::factory()->create(['role' => UserRole::Uitvoerder, 'name' => 'Peter Uitvoerder']);
    }

    public function test_uitvoerder_cannot_reach_crm_pages(): void
    {
        $uitvoerder = $this->uitvoerder();

        $this->actingAs($uitvoerder)->get('/aanvragen')->assertForbidden();
        $this->actingAs($uitvoerder)->get('/klanten')->assertForbidden();
        $this->actingAs($uitvoerder)->get('/offertes')->assertForbidden();
        $this->actingAs($uitvoerder)->get('/zoeken')->assertForbidden();
        $this->actingAs($uitvoerder)->postJson('/nova', ['message' => 'test'])->assertForbidden();
    }

    public function test_uitvoerder_sees_a_simplified_dashboard(): void
    {
        $uitvoerder = $this->uitvoerder();
        Task::factory()->create(['owner_id' => $uitvoerder->id, 'title' => 'Stucwerk afronden']);

        $response = $this->actingAs($uitvoerder)->get('/');

        $response->assertOk();
        $response->assertSee('Jouw taken');
        $response->assertSee('Stucwerk afronden');
        $response->assertDontSee('Nieuwe aanvragen');
    }

    public function test_uitvoerder_sees_only_own_projects(): void
    {
        $uitvoerder = $this->uitvoerder();

        $own = Project::factory()->create(['name' => 'Eigen Badkamer']);
        $own->craftsmen()->attach($uitvoerder);
        $other = Project::factory()->create(['name' => 'Andermans Keuken']);

        $response = $this->actingAs($uitvoerder)->get('/projecten');

        $response->assertOk();
        $response->assertSee('Eigen Badkamer');
        $response->assertDontSee('Andermans Keuken');

        $this->actingAs($uitvoerder)->get('/projecten/'.$own->id)->assertOk();
        $this->actingAs($uitvoerder)->get('/projecten/'.$other->id)->assertForbidden();
    }

    public function test_uitvoerder_sees_only_own_tasks_and_cannot_touch_others(): void
    {
        $uitvoerder = $this->uitvoerder();
        $collega = User::factory()->create();

        Task::factory()->create(['owner_id' => $uitvoerder->id, 'title' => 'Eigen taak']);
        $andermans = Task::factory()->create(['owner_id' => $collega->id, 'title' => 'Andermans taak']);

        $response = $this->actingAs($uitvoerder)->get('/taken');
        $response->assertOk();
        $response->assertSee('Eigen taak');
        $response->assertDontSee('Andermans taak');

        $this->actingAs($uitvoerder)
            ->patchJson('/taken/'.$andermans->id.'/status', ['status' => 'afgerond'])
            ->assertForbidden();
    }

    public function test_werkvoorbereider_has_crm_access(): void
    {
        $werkvoorbereider = User::factory()->create(['role' => UserRole::Werkvoorbereider]);

        foreach (['/aanvragen', '/klanten', '/projecten', '/planning'] as $url) {
            $this->actingAs($werkvoorbereider)->get($url)->assertOk();
        }

        $this->actingAs($werkvoorbereider)->get('/team')->assertForbidden();
    }

    public function test_admin_still_has_full_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach (['/aanvragen', '/klanten', '/offertes', '/aandacht', '/automations'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
