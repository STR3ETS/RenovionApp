<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_api_docs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/docs/api')
            ->assertOk()
            ->assertSee('X-Intake-Token')
            ->assertSee('/api/aanvragen');
    }

    public function test_uitvoerder_cannot_view_the_api_docs(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get('/docs/api')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/docs/api')->assertRedirect('/login');
    }
}
