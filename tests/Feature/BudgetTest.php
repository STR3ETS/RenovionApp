<?php

namespace Tests\Feature;

use App\Enums\CalculationLineType;
use App\Enums\UserRole;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetOption;
use App\Models\Customer;
use App\Models\PriceItem;
use App\Models\Project;
use App\Models\User;
use App\Support\BudgetChapterLibrary;
use App\Support\BudgetTotals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private function budget(array $attributes = []): Budget
    {
        return Budget::create([
            'number' => Budget::nextNumber(),
            'name' => 'Badkamerrenovatie',
            'status' => Budget::STATUS_DRAFT,
            'hourly_rate' => 55,
            'vat_pct' => 21,
            ...$attributes,
        ]);
    }

    private function lineOn(Budget $budget, array $line): BudgetLine
    {
        $chapter = $budget->chapters()->firstOrCreate(['code' => '41'], ['name' => 'Tegelwerk']);
        $paragraph = $chapter->paragraphs()->firstOrCreate(['code' => '.01'], ['name' => 'Wandtegels']);

        return $paragraph->lines()->create($line);
    }

    public function test_a_line_multiplies_quantity_by_hours_material_and_the_rest(): void
    {
        $budget = $this->budget();

        $line = $this->lineOn($budget, [
            'description' => 'Wandtegels zetten',
            'quantity' => 20,
            'unit' => 'm2',
            'norm_hours' => 0.75,
            'material' => 32.50,
            'equipment' => 1.25,
            'subcontracting' => 0,
            'other' => 2,
        ]);

        $costs = $line->costs(55);

        // 20 m2 maal 0,75 uur is 15 uur, tegen 55 euro is 825 euro arbeid.
        $this->assertSame(15.0, $costs['uren']);
        $this->assertSame(825.0, $costs['arbeid']);
        $this->assertSame(650.0, $costs['materiaal']);
        $this->assertSame(25.0, $costs['materieel']);
        $this->assertSame(40.0, $costs['overig']);
        $this->assertSame(1540.0, $costs['totaal']);
    }

    public function test_a_line_can_override_the_budget_rate(): void
    {
        $budget = $this->budget();

        $line = $this->lineOn($budget, [
            'description' => 'Specialistisch loodgieterswerk',
            'quantity' => 4,
            'unit' => 'uur',
            'norm_hours' => 1,
            'hourly_rate' => 85,
        ]);

        $this->assertSame(340.0, $line->costs(55)['arbeid']);
    }

    public function test_text_lines_do_not_count_towards_the_total(): void
    {
        $budget = $this->budget();

        $this->lineOn($budget, [
            'description' => 'Wandtegels',
            'quantity' => 10,
            'norm_hours' => 1,
        ]);

        $this->lineOn($budget, [
            'type' => BudgetLine::TYPE_TEXT,
            'description' => 'Let op: tegels worden door de klant aangeleverd.',
            'quantity' => 999,
            'material' => 999,
        ]);

        $totals = $budget->fresh()->totals();

        $this->assertSame(1, $totals->lineCount);
        $this->assertSame(550.0, $totals->direct);
    }

    public function test_the_markups_compound_over_each_other(): void
    {
        $budget = $this->budget();

        $this->lineOn($budget, [
            'description' => 'Werk',
            'quantity' => 1,
            'material' => 10000,
        ]);

        $budget->markups()->create(['name' => 'Algemene kosten', 'percentage' => 10, 'position' => 0]);
        $budget->markups()->create(['name' => 'Winst en risico', 'percentage' => 10, 'position' => 1]);

        $totals = $budget->fresh()->totals();

        $this->assertSame(10000.0, $totals->direct);
        // Algemene kosten over 10.000 is 1.000; winst en risico over 11.000 is 1.100.
        $this->assertSame(1000.0, $totals->markups[0]['amount']);
        $this->assertSame(11000.0, $totals->markups[1]['base']);
        $this->assertSame(1100.0, $totals->markups[1]['amount']);
        $this->assertSame(12100.0, $totals->totalExcludingVat);
        $this->assertSame(2541.0, $totals->vat);
        $this->assertSame(14641.0, $totals->totalIncludingVat);
        // Marge is het deel van de aanneemsom; opslag is dat bedrag op de kosten.
        $this->assertSame(17.36, $totals->margin);
        $this->assertSame(21.0, $totals->surcharge);
    }

    public function test_margin_and_surcharge_convert_both_ways(): void
    {
        // Twintig procent marge vraagt een opslag van vijfentwintig procent.
        $this->assertSame(25.0, BudgetTotals::surchargeForMargin(20));
        $this->assertSame(20.0, BudgetTotals::marginForSurcharge(25));
        // Honderd procent marge bestaat niet: dan is de kostprijs nul.
        $this->assertNull(BudgetTotals::surchargeForMargin(100));
    }

    public function test_approved_options_count_as_extra_work_with_the_same_markup(): void
    {
        $budget = $this->budget();

        $this->lineOn($budget, ['description' => 'Werk', 'quantity' => 1, 'material' => 10000]);
        $budget->markups()->create(['name' => 'Algemene kosten', 'percentage' => 10, 'position' => 0]);

        $budget->options()->create([
            'description' => 'Vloerverwarming',
            'quantity' => 1,
            'unit' => 'pst',
            'price' => 2000,
            'status' => BudgetOption::STATUS_OPEN,
        ]);

        $budget->options()->create([
            'description' => 'Tweede wastafel',
            'quantity' => 1,
            'unit' => 'pst',
            'price' => 1000,
            'status' => BudgetOption::STATUS_APPROVED,
        ]);

        $totals = $budget->fresh()->totals();

        // Met tien procent staart wordt 2.000 euro aan opties 2.200 euro.
        $this->assertSame(2200.0, $totals->openOptions);
        $this->assertSame(1100.0, $totals->approvedExtras);
        $this->assertSame(11000.0, $totals->totalExcludingVat);
        // De aanneemsom is de som plus het goedgekeurde meerwerk.
        $this->assertSame(12100.0, $totals->contractSum());
    }

    public function test_lines_with_a_quantity_but_no_price_are_flagged(): void
    {
        $budget = $this->budget();

        $this->lineOn($budget, ['description' => 'Nog uitzoeken', 'quantity' => 5, 'unit' => 'st']);
        $this->lineOn($budget, ['description' => 'Wel geprijsd', 'quantity' => 5, 'material' => 10]);
        // Zonder hoeveelheid is er niets mis: die regel is bewust nog nul.
        $this->lineOn($budget, ['description' => 'Nul', 'quantity' => 0]);

        $this->assertSame(1, $budget->fresh()->totals()->incompleteLines);
    }

    public function test_dutch_numbers_are_parsed_either_way(): void
    {
        $this->assertSame(1234.56, BudgetLine::parseNumber('1.234,56'));
        $this->assertSame(1234.56, BudgetLine::parseNumber('1234.56'));
        $this->assertSame(0.75, BudgetLine::parseNumber('0,75'));
        $this->assertSame(55.0, BudgetLine::parseNumber('EUR 55'));
        $this->assertSame(0.0, BudgetLine::parseNumber(''));
        $this->assertSame(0.0, BudgetLine::parseNumber('onzin'));
    }

    public function test_price_per_square_metre_needs_a_floor_area(): void
    {
        $budget = $this->budget(['floor_area' => 8]);
        $this->lineOn($budget, ['description' => 'Werk', 'quantity' => 1, 'material' => 8000]);

        $this->assertSame(1000.0, $budget->fresh()->totals()->pricePerSquareMetre);

        $zonder = $this->budget(['name' => 'Zonder oppervlak']);
        $this->lineOn($zonder, ['description' => 'Werk', 'quantity' => 1, 'material' => 8000]);

        $this->assertNull($zonder->fresh()->totals()->pricePerSquareMetre);
    }

    public function test_a_new_budget_starts_with_the_skeleton_for_the_work(): void
    {
        $calculator = User::factory()->create(['role' => UserRole::Calculator]);
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'type' => 'Badkamerrenovatie',
        ]);

        $this->actingAs($calculator)->post(route('budgets.store'), [
            'name' => 'Badkamer familie De Groot',
            'project_id' => $project->id,
        ])->assertRedirect();

        $budget = Budget::latest('id')->first();

        // Het badkamerskelet: sloop, leidingwerk, tegelwerk en sanitair.
        $codes = $budget->chapters->pluck('code');
        $this->assertTrue($codes->contains('10'));
        $this->assertTrue($codes->contains('41'));
        $this->assertTrue($codes->contains('74'));

        // En de staart plus het betaalschema staan klaar.
        $this->assertCount(count(Budget::DEFAULT_MARKUPS), $budget->markups);
        $this->assertCount(count(BudgetChapterLibrary::defaultTerms()), $budget->terms);
        $this->assertSame(100.0, $budget->termsCoverage());
    }

    public function test_a_line_from_the_price_list_lands_in_the_right_cost_column(): void
    {
        $calculator = User::factory()->create(['role' => UserRole::Calculator]);
        $budget = $this->budget();
        $chapter = $budget->chapters()->create(['code' => '41', 'name' => 'Tegelwerk']);
        $paragraph = $chapter->paragraphs()->create(['code' => '.01', 'name' => 'Wandtegels']);

        $item = PriceItem::factory()->create([
            'code' => 'MAT-001',
            'name' => 'Wandtegel 30x60 wit',
            'type' => CalculationLineType::Materiaal,
            'unit' => 'm2',
            'unit_price' => 28,
            'surcharge_pct' => 10,
            'index_factor' => 1,
        ]);

        $this->actingAs($calculator)->post(route('budget-lines.store', $paragraph), [
            'price_item_id' => $item->id,
            'quantity' => '12,5',
        ])->assertSessionHas('success');

        $line = $paragraph->lines()->first();

        $this->assertSame('Wandtegel 30x60 wit', $line->description);
        $this->assertSame('m2', $line->unit);
        $this->assertSame(12.5, (float) $line->quantity);
        // 28 euro plus tien procent opslag is 30,80 in de materiaalkolom.
        $this->assertSame(30.80, (float) $line->material);
        $this->assertSame(0.0, (float) $line->norm_hours);
        $this->assertSame('Prijslijst MAT-001', $line->source);
    }

    public function test_a_budget_cannot_be_finalised_while_lines_lack_a_price(): void
    {
        $calculator = User::factory()->create(['role' => UserRole::Calculator]);
        $budget = $this->budget();
        $this->lineOn($budget, ['description' => 'Nog uitzoeken', 'quantity' => 5]);

        $this->actingAs($calculator)
            ->post(route('budgets.finalise', $budget))
            ->assertSessionHas('error');

        $this->assertTrue($budget->fresh()->isDraft());

        $this->lineOn($budget, ['description' => 'Wel geprijsd', 'quantity' => 1, 'material' => 100]);
        $budget->chapters->first()->paragraphs->first()->lines()->where('description', 'Nog uitzoeken')
            ->update(['material' => 50]);

        $this->actingAs($calculator)
            ->post(route('budgets.finalise', $budget))
            ->assertSessionHas('success');

        $this->assertFalse($budget->fresh()->isDraft());
    }

    public function test_a_final_budget_is_locked_until_it_is_reopened(): void
    {
        $calculator = User::factory()->create(['role' => UserRole::Calculator]);
        $budget = $this->budget(['status' => Budget::STATUS_FINAL, 'finalised_at' => now()]);
        $chapter = $budget->chapters()->create(['code' => '41', 'name' => 'Tegelwerk']);
        $paragraph = $chapter->paragraphs()->create(['code' => '.01', 'name' => 'Wandtegels']);

        $this->actingAs($calculator)
            ->post(route('budget-lines.store', $paragraph), ['description' => 'Extra regel', 'quantity' => '1'])
            ->assertStatus(422);

        $this->actingAs($calculator)->post(route('budgets.reopen', $budget))->assertSessionHas('success');

        $this->assertSame(2, $budget->fresh()->version);

        $this->actingAs($calculator)
            ->post(route('budget-lines.store', $paragraph), ['description' => 'Extra regel', 'quantity' => '1'])
            ->assertSessionHas('success');
    }

    public function test_a_craftsman_cannot_reach_budgets(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get(route('budgets.index'))->assertForbidden();
    }
}
