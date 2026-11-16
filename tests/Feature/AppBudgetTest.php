<?php

namespace Tests\Feature;

use App\Enums\CalculationLineType;
use App\Enums\UserRole;
use App\Models\Budget;
use App\Models\BudgetOption;
use App\Models\BudgetParagraph;
use App\Models\PriceItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Begrotingen als eigen schermen in de app: de lijst en de calculatietafel met al zijn acties. */
class AppBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function token(UserRole $role = UserRole::Calculator): string
    {
        return User::factory()->create(['role' => $role])->createToken('test')->plainTextToken;
    }

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

    private function paragraph(Budget $budget): BudgetParagraph
    {
        $chapter = $budget->chapters()->firstOrCreate(['code' => '41'], ['name' => 'Tegelwerk', 'position' => 0]);

        return $chapter->paragraphs()->firstOrCreate(['code' => '.01'], ['name' => 'Wandtegels', 'position' => 0]);
    }

    public function test_the_list_shows_totals_filters_and_search(): void
    {
        $project = Project::factory()->create(['name' => 'Woning Velp']);
        $budget = $this->budget(['project_id' => $project->id]);
        $this->paragraph($budget)->lines()->create(['description' => 'Wandtegels zetten', 'quantity' => 20, 'unit' => 'm2', 'norm_hours' => 1]);
        $this->budget(['name' => 'Keuken', 'status' => Budget::STATUS_FINAL]);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.budgets.index'))->assertOk()
            ->assertJsonPath('counts.concept', 1)
            ->assertJsonPath('counts.definitief', 1)
            ->assertJsonPath('links.create', route('budgets.create', [], false))
            ->assertJsonCount(2, 'budgets');

        $this->withToken($token)->getJson(route('api.app.budgets.index', ['status' => 'concept']))->assertOk()
            ->assertJsonCount(1, 'budgets')
            ->assertJsonPath('budgets.0.name', 'Badkamerrenovatie')
            ->assertJsonPath('budgets.0.status.label', 'Concept')
            ->assertJsonPath('budgets.0.sub', $budget->number.', Woning Velp, 1 hoofdstuk, 1 regel')
            // 20 uur tegen 55 euro, zonder staart.
            ->assertJsonPath('budgets.0.total', 1100)
            ->assertJsonPath('budgets.0.path', route('budgets.show', $budget, false));

        $this->withToken($token)->getJson(route('api.app.budgets.index', ['q' => 'keuk']))->assertOk()
            ->assertJsonCount(1, 'budgets')
            ->assertJsonPath('budgets.0.status.label', 'Definitief');
    }

    public function test_the_detail_shows_chapters_lines_markups_options_terms_and_scope(): void
    {
        $budget = $this->budget(['floor_area' => 10, 'scope' => ['incl' => ['Tegels'], 'excl' => ['Sanitair']]]);
        $line = $this->paragraph($budget)->lines()->create(['description' => 'Nog uitzoeken', 'quantity' => 5, 'unit' => 'm2']);
        $budget->markups()->create(['name' => 'Algemene kosten', 'percentage' => 10, 'position' => 0]);
        $budget->terms()->create(['description' => 'Bij opdracht', 'percentage' => 30, 'status' => 'gepland', 'position' => 0]);

        $this->withToken($this->token())->getJson(route('api.app.budgets.show', $budget))->assertOk()
            ->assertJsonPath('draft', true)
            ->assertJsonPath('incomplete', 1)
            ->assertJsonPath('chapters.0.code', '41')
            ->assertJsonPath('chapters.0.paragraphs.0.label', '41.01 Wandtegels')
            ->assertJsonPath('chapters.0.paragraphs.0.lines.0.id', $line->id)
            ->assertJsonPath('chapters.0.paragraphs.0.lines.0.quantity', '5')
            ->assertJsonPath('chapters.0.paragraphs.0.lines.0.material', '')
            ->assertJsonPath('chapters.0.paragraphs.0.lines.0.incomplete', true)
            ->assertJsonPath('markups.0.percentage', '10')
            ->assertJsonPath('terms.0.status.label', 'Gepland')
            ->assertJsonPath('terms_summary.ok', false)
            ->assertJsonPath('scope.excl', ['Sanitair'])
            ->assertJsonPath('kpis.floor_area', '10 m2');
    }

    public function test_lines_are_added_edited_and_removed_and_the_whole_budget_comes_back(): void
    {
        $budget = $this->budget();
        $paragraph = $this->paragraph($budget);
        $token = $this->token();

        $this->withToken($token)->postJson(route('api.app.budget-lines.store', $paragraph), ['description' => 'Wandtegels zetten', 'quantity' => '20', 'unit' => 'm2'])
            ->assertOk()->assertJsonPath('chapters.0.paragraphs.0.lines.0.description', 'Wandtegels zetten');
        $line = $paragraph->lines()->first();

        // Lege getallen stuurt de app als 0; Nederlandse notatie werkt.
        $this->withToken($token)->patchJson(route('api.app.budget-lines.update', $line), [
            'description' => 'Wandtegels zetten 30x60', 'quantity' => '20', 'unit' => 'm2',
            'norm_hours' => '0,75', 'material' => '32,50', 'equipment' => '0', 'subcontracting' => '0', 'other' => '0',
        ])->assertOk()
            ->assertJsonPath('chapters.0.paragraphs.0.lines.0.material', '32,5')
            // 15 uur tegen 55 euro plus 650 euro materiaal.
            ->assertJsonPath('kpis.direct', 1475);

        $this->withToken($token)->deleteJson(route('api.app.budget-lines.destroy', $line))
            ->assertOk()->assertJsonCount(0, 'chapters.0.paragraphs.0.lines');
    }

    public function test_a_line_from_the_price_list_lands_in_the_right_cost_column(): void
    {
        $budget = $this->budget();
        $paragraph = $this->paragraph($budget);
        $item = PriceItem::factory()->create([
            'code' => 'MAT-001', 'name' => 'Wandtegel 30x60 wit', 'type' => CalculationLineType::Materiaal,
            'unit' => 'm2', 'unit_price' => 28, 'surcharge_pct' => 10, 'index_factor' => 1,
        ]);

        $this->withToken($this->token())->postJson(route('api.app.budget-lines.store', $paragraph), ['price_item_id' => $item->id, 'description' => $item->name, 'unit' => 'm2', 'quantity' => '1'])
            ->assertOk()
            ->assertJsonPath('chapters.0.paragraphs.0.lines.0.material', '30,8')
            ->assertJsonPath('chapters.0.paragraphs.0.lines.0.source', 'Prijslijst MAT-001');
    }

    public function test_chapters_and_paragraphs_are_added_and_removed(): void
    {
        $budget = $this->budget();
        $token = $this->token();

        $response = $this->withToken($token)->postJson(route('api.app.budget-chapters.store', $budget), ['code' => '41'])
            ->assertOk()
            ->assertJsonPath('chapters.0.code', '41')
            ->assertJsonPath('chapters.0.paragraphs.0.label', '41.01 Algemeen');
        $chapterId = $response->json('chapters.0.id');

        $this->withToken($token)->postJson(route('api.app.budget-paragraphs.store', $chapterId), ['name' => 'Vloertegels'])
            ->assertOk()->assertJsonPath('chapters.0.paragraphs.1.label', '41.02 Vloertegels');

        $this->withToken($token)->postJson(route('api.app.budget-paragraphs.store', $chapterId), ['name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->withToken($token)->deleteJson(route('api.app.budget-chapters.destroy', $chapterId))
            ->assertOk()->assertJsonCount(0, 'chapters');
    }

    public function test_finalising_is_refused_with_unpriced_lines_and_a_final_budget_is_locked_until_reopened(): void
    {
        $budget = $this->budget();
        $line = $this->paragraph($budget)->lines()->create(['description' => 'Nog uitzoeken', 'quantity' => 5]);
        $token = $this->token();

        $this->withToken($token)->postJson(route('api.app.budgets.finalise', $budget))
            ->assertUnprocessable()->assertJsonPath('message', '1 regel heeft nog een hoeveelheid zonder prijs. Vul die eerst in.');
        $this->assertTrue($budget->fresh()->isDraft());

        $line->update(['material' => 50]);
        $this->withToken($token)->postJson(route('api.app.budgets.finalise', $budget))
            ->assertOk()->assertJsonPath('draft', false)->assertJsonPath('status.label', 'Definitief');

        $this->withToken($token)->postJson(route('api.app.budget-lines.store', $line->paragraph), ['description' => 'Extra', 'quantity' => '1'])
            ->assertUnprocessable()->assertJsonPath('message', 'Deze begroting is definitief. Heropen hem eerst.');
        $this->withToken($token)->patchJson(route('api.app.budgets.update', $budget), ['scope_incl' => 'Iets'])
            ->assertUnprocessable();

        $this->withToken($token)->postJson(route('api.app.budgets.reopen', $budget))
            ->assertOk()->assertJsonPath('draft', true)->assertJsonPath('version', 2);
    }

    public function test_markups_options_terms_and_scope_are_managed_from_the_app(): void
    {
        $budget = $this->budget();
        $this->paragraph($budget)->lines()->create(['description' => 'Werk', 'quantity' => 1, 'material' => 1000]);
        $token = $this->token();

        $response = $this->withToken($token)->postJson(route('api.app.budget-markups.store', $budget), ['name' => 'Algemene kosten', 'percentage' => '10'])
            ->assertOk()->assertJsonPath('markups.0.amount', 100)->assertJsonPath('kpis.total_excl', 1100);
        $markupId = $response->json('markups.0.id');
        $this->withToken($token)->patchJson(route('api.app.budget-markups.update', $markupId), ['percentage' => '20'])
            ->assertOk()->assertJsonPath('markups.0.amount', 200);
        $this->withToken($token)->deleteJson(route('api.app.budget-markups.destroy', $markupId))
            ->assertOk()->assertJsonCount(0, 'markups');

        $response = $this->withToken($token)->postJson(route('api.app.budget-options.store', $budget), ['description' => 'Inloopdouche', 'quantity' => '1', 'unit' => 'pst', 'price' => ''])
            ->assertOk()->assertJsonPath('options.0.needs_pricing', true)
            ->assertJsonPath('options.0.actions.*.value', [BudgetOption::STATUS_APPROVED, BudgetOption::STATUS_DECLINED]);
        $optionId = $response->json('options.0.id');
        $this->withToken($token)->patchJson(route('api.app.budget-options.update', $optionId), ['price' => '450'])
            ->assertOk()->assertJsonPath('options.0.amount', 450);
        $this->withToken($token)->patchJson(route('api.app.budget-options.update', $optionId), ['status' => 'akkoord'])
            ->assertOk()->assertJsonPath('options.0.status.label', 'Akkoord')->assertJsonPath('contract_sum', 1450);
        $this->withToken($token)->deleteJson(route('api.app.budget-options.destroy', $optionId))
            ->assertOk()->assertJsonCount(0, 'options');

        $response = $this->withToken($token)->postJson(route('api.app.budget-terms.store', $budget), ['description' => 'Bij opdracht', 'percentage' => '100'])
            ->assertOk()->assertJsonPath('terms.0.amount', 1000)->assertJsonPath('terms_summary.ok', true);
        $termId = $response->json('terms.0.id');
        $this->withToken($token)->patchJson(route('api.app.budget-terms.update', $termId), ['percentage' => '40'])
            ->assertOk()->assertJsonPath('terms.0.percentage', '40')->assertJsonPath('terms_summary.ok', false);
        $this->withToken($token)->deleteJson(route('api.app.budget-terms.destroy', $termId))
            ->assertOk()->assertJsonCount(0, 'terms');

        $this->withToken($token)->patchJson(route('api.app.budgets.update', $budget), ['scope_docs' => "Tekening A\n\nTekening B", 'scope_incl' => '', 'scope_excl' => 'Sanitair'])
            ->assertOk()
            ->assertJsonPath('scope.docs', ['Tekening A', 'Tekening B'])
            ->assertJsonPath('scope.incl', [])
            ->assertJsonPath('scope.excl', ['Sanitair']);
    }

    public function test_executors_cannot_open_budgets(): void
    {
        $budget = $this->budget();
        $token = $this->token(UserRole::Uitvoerder);

        $this->withToken($token)->getJson(route('api.app.budgets.index'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.budgets.show', $budget))->assertForbidden();
        $this->withToken($token)->postJson(route('api.app.budgets.finalise', $budget))->assertForbidden();
    }
}
