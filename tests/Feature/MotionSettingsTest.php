<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotionSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const APP = ['User-Agent' => 'Mozilla/5.0 RenovionApp'];

    public function test_animations_are_on_by_default(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertTrue($user->motion_enabled);
        $this->actingAs($user)->get('/')->assertSee('data-motion="on"', false);
    }

    public function test_the_settings_switch_and_the_website_button_share_one_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson(route('motion-settings.update'), ['enabled' => false])
            ->assertOk()->assertJson(['enabled' => false]);

        $this->assertFalse($user->fresh()->motion_enabled);
        $this->actingAs($user->fresh())->get('/')->assertSee('data-motion="off"', false);

        $this->actingAs($user)->patchJson(route('motion-settings.update'), ['enabled' => true])->assertOk();
        $this->assertTrue($user->fresh()->motion_enabled);
    }

    public function test_the_app_uses_its_own_page_build_up_and_not_the_website_layer(): void
    {
        $response = $this->actingAs(User::factory()->create())->withHeaders(self::APP)->get('/');

        $response->assertSee('html.enter-pending main', false);
        $response->assertSee('rel="expect" href="#motion-stage" blocking="render"', false);
        $response->assertSee('id="motion-stage"', false);
        $response->assertSee('data-motion="off"', false);
        $response->assertSeeInOrder(['enter-pending', '</head>', '<main', "classList.remove('enter-pending')"], false);
    }

    public function test_the_website_does_not_load_the_app_build_up(): void
    {
        $this->actingAs(User::factory()->create())->get('/')
            ->assertDontSee('id="motion-stage"', false)
            ->assertDontSee('html.enter-pending main', false);
    }

    public function test_the_setting_needs_a_yes_or_no(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson(route('motion-settings.update'), [])->assertUnprocessable();
        $this->assertTrue($user->fresh()->motion_enabled);
    }
}
