<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** De offerte als eigen scherm in de app, en het document zoals de klant het ziet (licht, met PDF). */
class AppQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function quote(array $attributes = [], string $state = 'concept'): Quote
    {
        $lead = Lead::factory()->for(Customer::factory()->create(['name' => 'Dhr. Ahmed', 'city' => 'Arnhem', 'email' => 'ahmed@example.test']))
            ->create(['service' => 'Complete renovatie']);
        $factory = Quote::factory();
        $factory = match ($state) {
            'viewed' => $factory->viewed(2),
            'sent' => $factory->sent(),
            default => $factory,
        };
        $quote = $factory->create(['customer_id' => $lead->customer_id, 'lead_id' => $lead->id, ...$attributes]);
        QuoteLine::factory()->create(['quote_id' => $quote->id, 'description' => 'Badkamer: tegelwerk en sanitair', 'quantity' => 1, 'unit' => 'post', 'unit_price' => 1000, 'vat_rate' => 21, 'total' => 1000]);
        $quote->recalculateTotals();

        return $quote->fresh();
    }

    public function test_a_sent_quote_shows_its_signals_lines_link_and_follow_up_actions(): void
    {
        $quote = $this->quote(['sent_at' => now()->subDays(6)], 'viewed');
        $token = $this->admin()->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.quotes.show', $quote))->assertOk()
            ->assertJsonPath('number', $quote->number)
            ->assertJsonPath('title', 'Complete renovatie')
            ->assertJsonPath('status.value', 'bekeken')
            ->assertJsonPath('signals.0.color', 'red')
            ->assertJsonPath('signals.0.text', 'Staat 6 dagen open, 2× bekeken: vandaag opvolgen')
            ->assertJsonPath('actions.*.value', ['opvolgen', 'akkoord', 'afgewezen'])
            ->assertJsonPath('public_url', $quote->publicUrl())
            ->assertJsonPath('lines.0.quantity', '1 post')
            ->assertJsonPath('total', 1210)
            ->assertJsonPath('links.document', route('quotes.document', $quote, false))
            ->assertJsonPath('links.builder', null);
    }

    public function test_a_concept_is_sent_from_the_app_and_then_frozen_as_a_version(): void
    {
        $quote = $this->quote();
        $token = $this->admin()->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.quotes.show', $quote))->assertOk()
            ->assertJsonPath('concept', true)
            ->assertJsonPath('public_url', null)
            ->assertJsonPath('mail', null)
            ->assertJsonPath('actions.0.value', 'verstuurd')
            ->assertJsonPath('links.builder', route('quotes.builder', $quote, false));

        $this->withToken($token)->patchJson(route('api.app.quotes.status', $quote), ['status' => 'verstuurd'])
            ->assertOk()->assertJsonPath('label', 'Verstuurd');
        $this->assertSame(QuoteStatus::Verstuurd, $quote->fresh()->status);
        $this->assertNotNull($quote->fresh()->sent_at);

        $this->withToken($token)->postJson(route('api.app.quotes.version', $quote), ['note' => 'Ander tegelwerk'])
            ->assertOk()->assertJsonPath('version', 2);
        $this->withToken($token)->getJson(route('api.app.quotes.show', $quote))->assertOk()
            ->assertJsonPath('version', 2)
            ->assertJsonPath('concept', true)
            ->assertJsonPath('versions.0.version', 1);

        $this->withToken($token)->postJson(route('api.app.quotes.version', $quote), ['note' => 'Nog een'])->assertUnprocessable();
    }

    public function test_accepting_in_the_app_leads_to_the_new_project_and_a_closed_quote_stays_closed(): void
    {
        $quote = $this->quote([], 'sent');
        $token = $this->admin()->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->patchJson(route('api.app.quotes.status', $quote), ['status' => 'akkoord'])->assertOk();
        $this->assertSame(route('projects.show', $quote->fresh()->project_id, false), $response->json('project'));

        $this->withToken($token)->patchJson(route('api.app.quotes.status', $quote), ['status' => 'afgewezen'])
            ->assertUnprocessable()->assertJsonPath('message', 'Deze offerte is al afgehandeld.');
        $this->withToken($token)->getJson(route('api.app.quotes.show', $quote))->assertOk()
            ->assertJsonPath('actions', [])
            ->assertJsonPath('links.project', route('projects.show', $quote->fresh()->project_id, false))
            ->assertJsonPath('project.path', route('projects.show', $quote->fresh()->project_id, false));
    }

    public function test_executors_cannot_open_quotes(): void
    {
        $quote = $this->quote();
        $token = User::factory()->create(['role' => UserRole::Uitvoerder])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.quotes.show', $quote))->assertForbidden();
    }

    public function test_the_document_shows_what_the_customer_sees_without_signing_or_counting_as_a_view(): void
    {
        $quote = $this->quote([], 'viewed');

        $this->actingAs($this->admin())
            ->withSession(['app_bundle' => 'https://localhost'])
            ->withHeader('User-Agent', 'Mozilla/5.0 RenovionApp/5')
            ->get(route('quotes.document', $quote))
            ->assertOk()
            ->assertSee('Zo ziet de klant het')
            ->assertSee('https://localhost/#/offerte/'.$quote->id, false)
            ->assertDontSee('Offerte ondertekenen');

        $this->assertSame(2, $quote->fresh()->viewed_count);
    }
}
