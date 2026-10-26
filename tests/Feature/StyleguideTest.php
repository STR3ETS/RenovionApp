<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StyleguideTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_styleguide_and_dashboard_redesign(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/stijlgids')
            ->assertOk()
            ->assertSee('Stijlgids')
            ->assertSee('Categoriekleuren');

        $this->actingAs($admin)->get('/redesign-dashboard')
            ->assertOk()
            ->assertSee('Ontwerpvoorstel')
            ->assertSee('Nu doen');
    }

    public function test_uitvoerder_cannot_view_the_design_pages(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get('/stijlgids')->assertForbidden();
        $this->actingAs($uitvoerder)->get('/redesign-dashboard')->assertForbidden();
    }
}
