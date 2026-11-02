<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotionSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_animations_are_on_by_default(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertTrue($user->motion);
        $this->actingAs($user)->get('/')->assertSee('data-motion', false);
    }

    public function test_a_user_can_turn_animations_off_and_on(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson(route('motion-settings.update'), ['enabled' => false])
            ->assertOk()->assertJson(['enabled' => false]);

        $this->assertFalse($user->fresh()->motion);
        $this->actingAs($user->fresh())->get('/')->assertDontSee('data-motion', false);

        $this->actingAs($user)->patchJson(route('motion-settings.update'), ['enabled' => true])->assertOk();
        $this->assertTrue($user->fresh()->motion);
    }

    public function test_content_stays_hidden_until_the_page_build_up_is_ready(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertSee('html.enter-pending main', false);
        $response->assertSee('rel="expect" href="#motion-stage" blocking="render"', false);
        $response->assertSee('id="motion-stage"', false);
        $response->assertSeeInOrder(['enter-pending', '</head>', '<main', "classList.remove('enter-pending')"], false);
    }

    public function test_the_setting_needs_a_yes_or_no(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson(route('motion-settings.update'), [])->assertUnprocessable();
        $this->assertTrue($user->fresh()->motion);
    }

    public function test_project_cards_and_the_project_header_are_linked_for_the_zoom(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($user)->get(route('projects.index'))
            ->assertSee('data-zoom-card', false)
            ->assertSee('data-fan', false);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('data-zoom-target', false)
            ->assertSee('data-pill', false);
    }
}
