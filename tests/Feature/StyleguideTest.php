<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StyleguideTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_styleguide(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/stijlgids')
            ->assertOk()
            ->assertSee('Stijlgids')
            ->assertSee('Categoriekleuren');
    }

    public function test_the_dashboard_uses_the_new_style(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/')
            ->assertOk()
            ->assertSee('Nu doen')
            ->assertSee('Vandaag gepland');
    }

    public function test_uitvoerder_cannot_view_the_styleguide(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get('/stijlgids')->assertForbidden();
    }
}
