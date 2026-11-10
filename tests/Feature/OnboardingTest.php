<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\OnboardingGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_checklist_reflects_what_is_set_up(): void
    {
        $admin = User::factory()->create(['name' => 'Imad Test']);

        $this->actingAs($admin)->get('/aan-de-slag')->assertOk()
            ->assertSee('Aan de slag')
            ->assertSee('Van aanvraag tot nazorg')
            ->assertSee('Instellen om te beginnen')
            ->assertSee('Eerste aanvraag invoeren')
            ->assertSee('Nog te doen')
            ->assertSee('Waar je wat vindt')
            ->assertSee('Uit het menu halen');

        $guide = app(OnboardingGuide::class);
        $this->assertFalse($guide->items($admin)->firstWhere('key', 'first_lead')['done']);

        Lead::factory()->create();

        $this->assertTrue($guide->items($admin)->firstWhere('key', 'first_lead')['done']);
        $this->assertFalse($guide->items($admin)->firstWhere('key', 'mail')['done'], 'in tests staat de mailer op array');
    }

    public function test_uitvoerder_gets_the_explanation_without_the_checklist(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();

        $this->actingAs($uitvoerder)->get('/aan-de-slag')->assertOk()
            ->assertSee('Jouw dag in')
            ->assertSee('Werkpakket afwerken')
            ->assertDontSee('Instellen om te beginnen')
            ->assertDontSee('Eerste aanvraag invoeren');
    }

    public function test_customers_cannot_open_the_onboarding_page(): void
    {
        $customer = Customer::factory()->create();
        $klant = User::factory()->create(['role' => UserRole::Klant, 'customer_id' => $customer->id]);

        $this->actingAs($klant)->get('/aan-de-slag')->assertForbidden();
    }

    public function test_onboarding_shows_in_the_menu_and_on_the_dashboard_until_dismissed(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $this->get('/')->assertOk()->assertSee('Aan de slag')->assertSee('stappen nog te doen');

        $this->post('/aan-de-slag/verbergen')->assertRedirect('/');
        $this->assertTrue(app(OnboardingGuide::class)->isDismissedFor($admin->fresh()));
        $this->get('/')->assertOk()->assertDontSee('stappen nog te doen');

        $this->post('/aan-de-slag/tonen')->assertRedirect('/aan-de-slag');
        $this->assertFalse(app(OnboardingGuide::class)->isDismissedFor($admin->fresh()));
        $this->get('/')->assertOk()->assertSee('stappen nog te doen');
    }

    public function test_dismissal_is_per_user(): void
    {
        $imad = User::factory()->create();
        $raphael = User::factory()->create();

        $this->actingAs($imad)->post('/aan-de-slag/verbergen');

        $this->assertTrue(app(OnboardingGuide::class)->isDismissedFor($imad->fresh()));
        $this->assertFalse(app(OnboardingGuide::class)->isDismissedFor($raphael->fresh()));
    }
}
