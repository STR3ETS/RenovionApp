<?php

namespace Tests\Feature;

use App\Models\Calculation;
use App\Models\Customer;
use App\Models\PriceItem;
use App\Models\User;
use App\Services\CalculationAssistant;
use Database\Seeders\PriceLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_calculation_can_be_created_with_confirmed_lines(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $priceItem = PriceItem::factory()->create(['name' => 'Wanden glad stucwerk', 'unit' => 'm2', 'unit_price' => 27.50, 'type' => 'arbeid']);

        $response = $this->actingAs($user)->post('/calculaties', [
            'title' => 'Calculatie badkamer Jansen',
            'customer_id' => $customer->id,
            'description' => 'Badkamer 6 m2 renoveren.',
            'lines' => [
                ['price_item_id' => $priceItem->id, 'type' => 'arbeid', 'description' => 'Wanden stucwerk badkamer', 'quantity' => 24, 'unit' => 'm2', 'unit_price' => 27.50],
                ['type' => 'stelpost', 'description' => 'Sanitair (stelpost)', 'quantity' => 1, 'unit' => 'post', 'unit_price' => 3500],
            ],
        ]);

        $calculation = Calculation::firstWhere('title', 'Calculatie badkamer Jansen');
        $response->assertRedirect(route('calculations.show', $calculation));

        $this->assertSame(2, $calculation->lines()->count());
        $this->assertDatabaseHas('calculation_lines', [
            'calculation_id' => $calculation->id,
            'price_item_id' => $priceItem->id,
            'price_source' => 'Renovion praktijkprijzen',
            'price_edition' => '2026',
        ]);
        $this->assertDatabaseHas('timeline_events', ['customer_id' => $customer->id, 'type' => 'calculatie']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'calculation', 'action' => 'aangemaakt']);
    }

    public function test_totals_include_risk_margin_and_vat(): void
    {
        $calculation = Calculation::factory()->create(['risk_pct' => 5, 'margin_pct' => 15, 'vat_pct' => 21]);
        $calculation->addLine(['type' => 'arbeid', 'description' => 'Stucwerk', 'quantity' => 10, 'unit_price' => 27.50]);
        $calculation->refresh()->load('lines');

        $this->assertEqualsWithDelta(275.00, $calculation->subtotal(), 0.001);
        $this->assertEqualsWithDelta(13.75, $calculation->riskAmount(), 0.001);
        $this->assertEqualsWithDelta(43.31, $calculation->marginAmount(), 0.001);
        $this->assertEqualsWithDelta(332.06, $calculation->totalExcl(), 0.001);
        $this->assertEqualsWithDelta(401.79, $calculation->totalIncl(), 0.01);
    }

    public function test_lines_can_be_added_updated_and_removed(): void
    {
        $user = User::factory()->create();
        $calculation = Calculation::factory()->create();

        $this->actingAs($user)->post('/calculaties/'.$calculation->id.'/regels', [
            'type' => 'materiaal', 'description' => 'Vloertegels', 'quantity' => 8, 'unit' => 'm2', 'unit_price' => 32.50,
        ])->assertRedirect(route('calculations.show', $calculation));

        $line = $calculation->lines()->first();
        $this->assertNotNull($line);

        $this->actingAs($user)->patch('/calculaties/'.$calculation->id.'/regels/'.$line->id, ['quantity' => 12]);
        $this->assertEqualsWithDelta(12, (float) $line->refresh()->quantity, 0.001);

        $this->actingAs($user)->delete('/calculaties/'.$calculation->id.'/regels/'.$line->id);
        $this->assertSame(0, $calculation->lines()->count());
    }

    public function test_a_definitieve_calculation_is_locked(): void
    {
        $user = User::factory()->create();
        $calculation = Calculation::factory()->definitief()->create();

        $this->actingAs($user)->post('/calculaties/'.$calculation->id.'/regels', [
            'type' => 'arbeid', 'description' => 'X', 'quantity' => 1, 'unit_price' => 10,
        ])->assertForbidden();

        $this->actingAs($user)->delete('/calculaties/'.$calculation->id)->assertForbidden();
    }

    public function test_price_library_can_be_searched(): void
    {
        $user = User::factory()->create();
        PriceItem::factory()->create(['name' => 'Plafond glad stucwerk']);
        PriceItem::factory()->create(['name' => 'Vloertegels leggen']);

        $this->actingAs($user)->getJson('/prijsitems?q=stucwerk')
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Plafond glad stucwerk')
            ->assertJsonCount(1, 'items');
    }

    public function test_ai_proposal_requires_configuration(): void
    {
        config(['renovion.ai.api_key' => null]);

        $this->actingAs(User::factory()->create())
            ->postJson('/calculaties/ai-voorstel', ['description' => 'Badkamer renoveren 6m2'])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'niet geconfigureerd'));
    }

    public function test_ai_proposal_returns_reviewable_lines(): void
    {
        config(['renovion.ai.api_key' => 'test-key']);

        $this->mock(CalculationAssistant::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('proposeLines')->once()->andReturn([
                'lines' => [['type' => 'arbeid', 'description' => 'Stucwerk wanden', 'quantity' => 24, 'unit' => 'm2', 'unit_price' => 27.5, 'surcharge_pct' => 0]],
                'note' => 'Uitgegaan van 24 m2 wandoppervlak.',
            ]);
        });

        $this->actingAs(User::factory()->create())
            ->postJson('/calculaties/ai-voorstel', ['description' => 'Badkamer 6 m2 stucwerk', 'sources' => ['Renovion praktijkprijzen']])
            ->assertOk()
            ->assertJsonPath('lines.0.description', 'Stucwerk wanden')
            ->assertJsonPath('note', 'Uitgegaan van 24 m2 wandoppervlak.');
    }

    public function test_price_library_seeder_is_idempotent(): void
    {
        $this->seed(PriceLibrarySeeder::class);
        $count = PriceItem::count();
        $this->assertGreaterThan(20, $count);

        PriceItem::firstWhere('name', 'Stukadoor (uurtarief)')->update(['unit_price' => 65]);

        $this->seed(PriceLibrarySeeder::class);
        $this->assertSame($count, PriceItem::count());
        $this->assertEqualsWithDelta(65, (float) PriceItem::firstWhere('name', 'Stukadoor (uurtarief)')->unit_price, 0.001);
    }

    public function test_uitvoerders_cannot_access_calculations(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $calculation = Calculation::factory()->create();

        $this->actingAs($uitvoerder)->get('/calculaties')->assertForbidden();
        $this->actingAs($uitvoerder)->get('/calculaties/'.$calculation->id)->assertForbidden();
        $this->actingAs($uitvoerder)->getJson('/prijsitems?q=stuc')->assertForbidden();
    }
}
