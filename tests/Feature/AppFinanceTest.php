<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\UserRole;
use App\Models\Budget;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Financieel en nacalculatie als eigen schermen in de app. */
class AppFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function token(UserRole $role = UserRole::Directie): string
    {
        return User::factory()->create(['role' => $role])->createToken('test')->plainTextToken;
    }

    public function test_the_overview_has_the_figures_six_months_of_bars_and_the_debtors_and_creditors(): void
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory()->create(['name' => 'Fam. Bakker']), 'value' => 20000]);
        $invoice = Invoice::create([
            'number' => Invoice::nextNumber(),
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'type' => InvoiceType::Termijn,
            'description' => 'Eerste termijn',
            'date' => today()->subDays(20),
            'due_date' => today()->subDays(6),
            'amount' => 1000,
            'vat_pct' => 21,
            'status' => InvoiceStatus::Verzonden,
        ]);
        PurchaseInvoice::create([
            'number' => 'GAMMA-1', 'supplier' => 'Gamma', 'project_id' => $project->id, 'category' => 'materiaal',
            'date' => today(), 'due_date' => today()->addDays(30), 'amount' => 500, 'vat_pct' => 21, 'status' => PurchaseInvoice::STATUS_NEW,
        ]);

        $this->withToken($this->token())->getJson(route('api.app.finance.index'))->assertOk()
            ->assertJsonPath('kpis.debtors', 1210)
            ->assertJsonPath('kpis.debtors_overdue', 1210)
            ->assertJsonPath('kpis.creditors', 605)
            ->assertJsonCount(6, 'revenue')
            ->assertJsonCount(6, 'cashflow')
            ->assertJsonPath('debtors.0.title', 'Fam. Bakker')
            ->assertJsonPath('debtors.0.sub', $invoice->number.', 6 dagen te laat')
            ->assertJsonPath('debtors.0.path', route('invoices.show', $invoice, false))
            ->assertJsonPath('creditors.0.title', 'Gamma');
    }

    public function test_the_cost_review_compares_budgeted_actual_and_expected_per_project(): void
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'name' => 'Keuken Smit', 'value' => 11000, 'progress' => 50]);
        $budget = Budget::create([
            'number' => Budget::nextNumber(), 'project_id' => $project->id, 'customer_id' => $project->customer_id,
            'name' => 'Begroting', 'status' => Budget::STATUS_FINAL, 'hourly_rate' => 50, 'vat_pct' => 21, 'finalised_at' => now(),
        ]);
        $budget->chapters()->create(['code' => '41', 'name' => 'Tegelwerk'])
            ->paragraphs()->create(['code' => '.01', 'name' => 'Wandtegels'])
            ->lines()->create(['description' => 'Tegels', 'quantity' => 1, 'unit' => 'pst', 'material' => 5000]);
        // Het materiaal is al voor 6.000 gefactureerd en goedgekeurd: twintig procent over de begroting.
        PurchaseInvoice::create([
            'number' => 'MOSA-1', 'supplier' => 'Mosa', 'project_id' => $project->id, 'category' => 'materiaal',
            'date' => today(), 'due_date' => today()->addDays(30), 'amount' => 6000, 'vat_pct' => 21, 'status' => PurchaseInvoice::STATUS_APPROVED,
        ]);

        $response = $this->withToken($this->token())->getJson(route('api.app.cost-reviews.index'))->assertOk()
            ->assertJsonPath('projects.0.name', 'Keuken Smit')
            ->assertJsonPath('projects.0.path', route('projects.show', $project, false))
            ->assertJsonCount(5, 'projects.0.rows');

        $material = collect($response->json('projects.0.rows'))->firstWhere('name', 'Materiaal');
        $this->assertSame('+20%', $material['variance']);
        $this->assertSame('bg-red-100 text-red-800', $material['classes']);
        $this->assertStringContainsString($budget->number, $response->json('projects.0.sub'));
    }

    public function test_executors_cannot_open_the_money_screens(): void
    {
        $token = $this->token(UserRole::Uitvoerder);

        $this->withToken($token)->getJson(route('api.app.finance.index'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.cost-reviews.index'))->assertForbidden();
    }
}
