<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** De offertelijst en het offertedashboard als eigen schermen in de app. */
class AppQuoteListTest extends TestCase
{
    use RefreshDatabase;

    private function token(UserRole $role = UserRole::Admin): string
    {
        return User::factory()->create(['role' => $role])->createToken('test')->plainTextToken;
    }

    public function test_the_list_shows_quotes_with_signals_and_filters_on_status(): void
    {
        $lead = Lead::factory()->for(Customer::factory()->create(['name' => 'Dhr. Ahmed']))->create(['service' => 'Badkamerrenovatie']);
        $sent = Quote::factory()->sent()->create(['customer_id' => $lead->customer_id, 'lead_id' => $lead->id, 'sent_at' => now()->subDays(6), 'total' => 1210]);
        Quote::factory()->create(['customer_id' => $lead->customer_id]);
        $token = $this->token();

        $response = $this->withToken($token)->getJson(route('api.app.quotes.index'))->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('more', false)
            ->assertJsonPath('links.create', route('quotes.create', [], false));
        $row = collect($response->json('quotes'))->firstWhere('id', $sent->id);
        $this->assertSame('Dhr. Ahmed', $row['customer']);
        $this->assertSame('Badkamerrenovatie', $row['title']);
        $this->assertStringContainsString('6 dagen open', $row['sub']);
        $this->assertSame('red', $row['signal']['color']);
        $this->assertSame(route('quotes.show', $sent, false), $row['path']);

        $this->withToken($token)->getJson(route('api.app.quotes.index', ['status' => QuoteStatus::Verstuurd->value]))->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('status', 'verstuurd')
            ->assertJsonPath('quotes.0.id', $sent->id);

        $this->withToken($token)->getJson(route('api.app.quotes.index', ['status' => 'onzin']))->assertUnprocessable();
    }

    public function test_the_list_pages_per_25(): void
    {
        Quote::factory()->count(27)->create(['customer_id' => Customer::factory()->create()->id]);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.quotes.index'))->assertOk()
            ->assertJsonCount(25, 'quotes')->assertJsonPath('more', true);
        $this->withToken($token)->getJson(route('api.app.quotes.index', ['pagina' => 2]))->assertOk()
            ->assertJsonCount(2, 'quotes')->assertJsonPath('more', false);
    }

    public function test_the_dashboard_shows_the_figures_rows_and_lost_reasons(): void
    {
        $customer = Customer::factory()->create(['name' => 'Fam. de Vries']);
        $open = Quote::factory()->sent()->create(['customer_id' => $customer->id, 'sent_at' => now()->subDays(2), 'total' => 10000]);
        $open->questions()->create(['body' => 'Kan de douche ook links?']);
        $lostLead = Lead::factory()->create(['customer_id' => $customer->id, 'lost_reason' => 'Te duur']);
        Quote::factory()->sent()->create(['customer_id' => $customer->id, 'lead_id' => $lostLead->id, 'status' => QuoteStatus::Afgewezen, 'sent_at' => now()->subDays(3), 'total' => 5000]);

        $response = $this->withToken($this->token())->getJson(route('api.app.quote-dashboard', ['periode' => 'alles']))->assertOk()
            ->assertJsonPath('period', 'alles')
            ->assertJsonPath('kpis.0.label', 'Gewogen open')
            ->assertJsonPath('kpis.0.value', '€ 6.000')
            ->assertJsonPath('kpis.1.value', '2')
            ->assertJsonPath('lost.reasons.0.reason', 'Te duur')
            ->assertJsonPath('lost.reasons.0.width', 100);

        $row = collect($response->json('rows'))->firstWhere('id', $open->id);
        $this->assertSame('Open', $row['state']['label']);
        $this->assertSame('1 vraag', $row['questions']);
        $this->assertSame('kans 60%, gewogen € 6.000', $row['note']);
        $this->assertSame(route('quotes.show', $open, false), $row['path']);
    }

    public function test_executors_cannot_open_the_sales_screens(): void
    {
        $token = $this->token(UserRole::Uitvoerder);

        $this->withToken($token)->getJson(route('api.app.quotes.index'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.quote-dashboard'))->assertForbidden();
    }
}
