<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Aan de slag" als eigen scherm in de app: uitleg per rol, de checklist voor kantoor, verbergen en terughalen. */
class AppOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_staff_get_the_live_checklist_with_paths(): void
    {
        $token = User::factory()->create(['role' => UserRole::Admin])->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(route('api.app.onboarding'))->assertOk()
            ->assertJsonPath('crm', true)
            ->assertJsonPath('dismissed', false)
            ->assertJsonPath('hero.title', 'Van aanvraag tot nazorg, zonder losse lijstjes')
            ->assertJsonPath('flow.0.title', 'Aanvraag komt binnen')
            ->assertJsonPath('modules.0.path', '/');

        $items = collect($response->json('items'));
        $this->assertSame('/team', $items->firstWhere('key', 'team')['path']);
        $this->assertSame('/aanvragen/create', $items->firstWhere('key', 'first_lead')['path']);
        $this->assertNull($items->firstWhere('key', 'ai')['path']);
        $this->assertSame($response->json('progress.total'), $items->where('optional', false)->count());
        $this->assertNotEmpty($response->json('nova'));
    }

    public function test_executors_get_their_own_explanation_without_a_checklist(): void
    {
        $token = User::factory()->create(['role' => UserRole::Uitvoerder])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.onboarding'))->assertOk()
            ->assertJsonPath('crm', false)
            ->assertJsonPath('progress', null)
            ->assertJsonPath('items', [])
            ->assertJsonPath('nova', [])
            ->assertJsonPath('automations', [])
            ->assertJsonPath('hero.title', 'Jouw werk, zonder gedoe')
            ->assertJsonPath('rhythm.0', 'Kijk op Vandaag wat er staat.')
            ->assertJsonCount(5, 'flow');
    }

    public function test_hiding_and_restoring_from_the_app(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson(route('api.app.onboarding.dismiss'))->assertOk()->assertJsonPath('dismissed', true);
        $this->assertNotNull($user->fresh()->onboarding_dismissed_at);
        $this->withToken($token)->getJson(route('api.app.onboarding'))->assertOk()->assertJsonPath('dismissed', true);

        $this->withToken($token)->postJson(route('api.app.onboarding.restore'))->assertOk()->assertJsonPath('dismissed', false);
        $this->assertNull($user->fresh()->onboarding_dismissed_at);
    }

    public function test_the_website_still_redirects_after_hiding(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Sales]))
            ->post(route('onboarding.dismiss'))
            ->assertRedirect(route('dashboard'));
    }
}
