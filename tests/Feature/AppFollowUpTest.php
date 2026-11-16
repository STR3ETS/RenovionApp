<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Opvolging als eigen scherm in de app: dezelfde volgorde en staten als de website. */
class AppFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_leads_come_first_then_unplanned_then_today_and_later(): void
    {
        $later = Lead::factory()->create(['next_action' => 'Offerte nabellen', 'next_action_at' => now()->addDays(3), 'last_contact_at' => now()]);
        $late = Lead::factory()->for(Customer::factory()->create(['name' => 'Mevr. Jansen', 'phone' => '0612345678']))
            ->create(['service' => 'Stucwerk', 'next_action_at' => now()->subDays(2)]);
        $none = Lead::factory()->create(['next_action_at' => null]);
        Lead::factory()->status(LeadStatus::Verloren)->create();

        $response = $this->withToken(User::factory()->create()->createToken('test')->plainTextToken)
            ->getJson(route('api.app.follow-ups.index'))->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('rows.*.id', [$late->id, $none->id, $later->id])
            ->assertJsonPath('rows.0.key', 'laat')
            ->assertJsonPath('rows.0.label', 'Te laat')
            ->assertJsonPath('rows.0.name', 'Mevr. Jansen')
            ->assertJsonPath('rows.0.silent', true)
            ->assertJsonPath('rows.0.phone', '0612345678')
            ->assertJsonPath('rows.0.path', route('leads.show', $late, false))
            ->assertJsonPath('rows.1.next', 'zet een vervolgstap')
            ->assertJsonPath('rows.2.silent', false)
            ->assertJsonPath('filters.*.value', ['laat', 'niets', 'vandaag', 'later'])
            ->assertJsonPath('filters.2.count', 0);

        $this->assertStringStartsWith('Stucwerk, nog geen contact geweest', $response->json('rows.0.sub'));
        $this->assertStringStartsWith('Offerte nabellen, ', $response->json('rows.2.next'));
    }

    public function test_the_web_page_still_filters(): void
    {
        Lead::factory()->for(Customer::factory()->create(['name' => 'Te Laat BV']))->create(['next_action_at' => now()->subDays(2)]);
        Lead::factory()->for(Customer::factory()->create(['name' => 'Niets Gepland BV']))->create(['next_action_at' => null]);

        $this->actingAs(User::factory()->create())->get(route('follow-ups.index', ['filter' => 'laat']))
            ->assertOk()->assertSee('Te Laat BV')->assertDontSee('Niets Gepland BV');
    }

    public function test_executors_cannot_open_follow_ups(): void
    {
        $token = User::factory()->create(['role' => UserRole::Uitvoerder])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.follow-ups.index'))->assertForbidden();
    }
}
