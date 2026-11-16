<?php

namespace Tests\Feature;

use App\Enums\CalculationStatus;
use App\Enums\UserRole;
use App\Models\Calculation;
use App\Models\Customer;
use App\Models\PriceItem;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Calculaties als eigen schermen in de app: regels, opslagen, status, verwijderen en offerte maken. */
class AppCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function token(UserRole $role = UserRole::Admin): string
    {
        return User::factory()->create(['role' => $role])->createToken('test')->plainTextToken;
    }

    public function test_the_list_shows_calculations_with_their_total(): void
    {
        $calculation = Calculation::factory()->for(Customer::factory()->create(['name' => 'Fam. Jansen']))->create(['title' => 'Badkamer Jansen', 'risk_pct' => 0, 'margin_pct' => 0]);
        $calculation->addLine(['type' => 'arbeid', 'description' => 'Stucwerk', 'quantity' => 10, 'unit_price' => 30]);

        $this->withToken($this->token())->getJson(route('api.app.calculations.index'))->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('calculations.0.title', 'Badkamer Jansen')
            ->assertJsonPath('calculations.0.total', 300)
            ->assertJsonPath('calculations.0.status.value', 'concept')
            ->assertJsonPath('calculations.0.path', route('calculations.show', $calculation, false))
            ->assertJsonPath('links.create', route('calculations.create', [], false));
    }

    public function test_lines_and_settings_are_edited_and_the_calculation_is_finalised(): void
    {
        $calculation = Calculation::factory()->create(['risk_pct' => 0, 'margin_pct' => 0, 'vat_pct' => 21]);
        $item = PriceItem::factory()->create(['name' => 'Wanden glad stucwerk', 'unit' => 'm2', 'unit_price' => 27.50, 'type' => 'arbeid']);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.price-items.search', ['q' => 'stucwerk']))->assertOk()
            ->assertJsonPath('items.0.id', $item->id);

        $this->withToken($token)->postJson(route('api.app.calculations.lines.store', $calculation), [
            'price_item_id' => $item->id, 'type' => 'arbeid', 'description' => 'Wanden stucwerk', 'quantity' => 10, 'unit' => 'm2', 'unit_price' => 27.50, 'surcharge_pct' => 0,
        ])->assertOk()->assertJsonPath('message', 'Regel toegevoegd.');
        $line = $calculation->lines()->first();

        $this->withToken($token)->patchJson(route('api.app.calculations.lines.update', [$calculation, $line]), ['quantity' => 20])->assertOk();
        $this->assertSame(550.0, $calculation->fresh()->subtotal());

        $this->withToken($token)->patchJson(route('api.app.calculations.update', $calculation), ['risk_pct' => 10, 'margin_pct' => 0, 'vat_pct' => 9])->assertOk()
            ->assertJsonPath('settings.risk_pct', 10)
            ->assertJsonPath('totals.1.value', 55)
            ->assertJsonPath('lines.0.source', fn (?string $source) => $source !== null)
            ->assertJsonPath('lines.0.total', 550);

        $this->withToken($token)->patchJson(route('api.app.calculations.update', $calculation), ['risk_pct' => 500])->assertUnprocessable();

        $this->withToken($token)->patchJson(route('api.app.calculations.update', $calculation), ['status' => 'definitief'])->assertOk()
            ->assertJsonPath('locked', true)
            ->assertJsonPath('status.label', 'Definitief');

        // Definitief: regels en verwijderen liggen vast, zoals op de website.
        $this->withToken($token)->patchJson(route('api.app.calculations.lines.update', [$calculation, $line]), ['quantity' => 1])->assertForbidden();
        $this->withToken($token)->deleteJson(route('api.app.calculations.lines.destroy', [$calculation, $line]))->assertForbidden();
        $this->withToken($token)->deleteJson(route('api.app.calculations.destroy', $calculation))->assertForbidden();

        $this->withToken($token)->patchJson(route('api.app.calculations.update', $calculation), ['status' => 'concept'])->assertOk()
            ->assertJsonPath('locked', false);
        $this->withToken($token)->deleteJson(route('api.app.calculations.lines.destroy', [$calculation, $line]))->assertOk();
        $this->assertSame(0, $calculation->lines()->count());

        $this->withToken($token)->deleteJson(route('api.app.calculations.destroy', $calculation))->assertOk()
            ->assertJsonPath('path', route('calculations.index', [], false));
        $this->assertModelMissing($calculation);
    }

    public function test_a_final_calculation_becomes_a_quote(): void
    {
        $calculation = Calculation::factory()->create();
        $calculation->addLine(['type' => 'arbeid', 'description' => 'Stucwerk', 'quantity' => 10, 'unit_price' => 30]);
        $token = $this->token();

        $this->withToken($token)->postJson(route('api.app.calculations.quote', $calculation))->assertUnprocessable()
            ->assertJsonPath('message', 'Maak de calculatie eerst definitief: de offerte legt vast met welke prijsset hij is gemaakt.');

        $calculation->update(['status' => CalculationStatus::Definitief]);
        $response = $this->withToken($token)->postJson(route('api.app.calculations.quote', $calculation))->assertCreated();
        $quote = Quote::firstWhere('calculation_id', $calculation->id);
        $response->assertJsonPath('path', route('quotes.show', $quote, false))
            ->assertJsonPath('builder', route('quotes.builder', $quote, false));
    }

    public function test_a_line_of_another_calculation_cannot_be_changed(): void
    {
        $calculation = Calculation::factory()->create();
        $other = Calculation::factory()->create();
        $line = $other->addLine(['type' => 'arbeid', 'description' => 'Stucwerk', 'quantity' => 1, 'unit_price' => 30]);

        $this->withToken($this->token())->patchJson(route('api.app.calculations.lines.update', [$calculation, $line]), ['quantity' => 5])->assertNotFound();
    }

    public function test_executors_cannot_open_calculations(): void
    {
        $calculation = Calculation::factory()->create();
        $token = $this->token(UserRole::Uitvoerder);

        $this->withToken($token)->getJson(route('api.app.calculations.index'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.calculations.show', $calculation))->assertForbidden();
    }
}
