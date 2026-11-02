<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotionPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_animations_are_on_by_default_and_can_be_switched_off(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->motion_enabled);
        $this->actingAs($user)->get('/')->assertSee('data-motion="on"', false);

        $this->actingAs($user)->post('/voorkeuren/beweging')->assertRedirect();
        $this->assertFalse($user->refresh()->motion_enabled);

        $this->actingAs($user)->get('/')->assertSee('data-motion="off"', false);
    }
}
