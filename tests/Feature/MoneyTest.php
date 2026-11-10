<?php

namespace Tests\Feature;

use App\Actions\BuildFinalSettlement;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\Budget;
use App\Models\BudgetOption;
use App\Models\BudgetTerm;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectDeduction;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CostReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Een project met een definitieve begroting van 10.000 directe kosten, tien
     * procent staart en twee termijnen van vijftig procent.
     *
     * @return array{0: Project, 1: Budget}
     */
    private function projectWithBudget(): array
    {
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'value' => 11000,
            'progress' => 50,
            'retention_type' => 'depot',
        ]);

        $budget = Budget::create([
            'number' => Budget::nextNumber(),
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'name' => 'Begroting '.$project->name,
            'status' => Budget::STATUS_FINAL,
            'hourly_rate' => 50,
            'vat_pct' => 21,
            'finalised_at' => now(),
        ]);

        $chapter = $budget->chapters()->create(['code' => '41', 'name' => 'Tegelwerk']);
        $paragraph = $chapter->paragraphs()->create(['code' => '.01', 'name' => 'Wandtegels']);

        // 100 uur arbeid tegen 50 euro, plus 5.000 materiaal: 10.000 direct.
        $paragraph->lines()->create([
            'description' => 'Tegelwerk',
            'quantity' => 100,
            'unit' => 'uur',
            'norm_hours' => 1,
        ]);
        $paragraph->lines()->create([
            'description' => 'Tegels',
            'quantity' => 1,
            'unit' => 'pst',
            'material' => 5000,
        ]);

        $budget->markups()->create(['name' => 'Algemene kosten', 'percentage' => 10, 'position' => 0]);
        $budget->terms()->create(['description' => 'Bij start', 'percentage' => 50, 'position' => 0]);
        $budget->terms()->create(['description' => 'Bij oplevering', 'percentage' => 50, 'position' => 1]);

        return [$project, $budget->fresh()];
    }

    public function test_a_term_invoice_takes_its_amount_from_the_budget(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administratie]);
        [$project, $budget] = $this->projectWithBudget();
        $term = $budget->terms->first();

        $this->actingAs($admin)->post(route('invoices.term', $term))->assertRedirect();

        $invoice = Invoice::firstWhere('budget_term_id', $term->id);

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceType::Termijn, $invoice->type);
        $this->assertSame(InvoiceStatus::Concept, $invoice->status);
        // Vijftig procent van 11.000 aanneemsom is 5.500.
        $this->assertSame('5500.00', $invoice->amount);
        $this->assertSame(1155.0, $invoice->vatAmount());
        $this->assertSame(6655.0, $invoice->total());
        // De termijn staat nu als gefactureerd in de begroting.
        $this->assertSame(BudgetTerm::STATUS_INVOICED, $term->fresh()->status);
    }

    public function test_the_same_term_cannot_be_invoiced_twice(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administratie]);
        [, $budget] = $this->projectWithBudget();
        $term = $budget->terms->first();

        $this->actingAs($admin)->post(route('invoices.term', $term));
        $this->actingAs($admin)->post(route('invoices.term', $term))->assertSessionHas('error');

        $this->assertSame(1, Invoice::where('budget_term_id', $term->id)->count());
    }

    public function test_sending_an_invoice_sets_the_payment_term(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administratie]);
        [, $budget] = $this->projectWithBudget();
        $term = $budget->terms->first();

        $this->actingAs($admin)->post(route('invoices.term', $term));
        $invoice = Invoice::firstWhere('budget_term_id', $term->id);

        $this->actingAs($admin)->patch(route('invoices.update', $invoice), [
            'status' => InvoiceStatus::Verzonden->value,
        ])->assertSessionHas('success');

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Verzonden, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertSame(today()->addDays(Invoice::PAYMENT_DAYS)->toDateString(), $invoice->due_date->toDateString());

        $this->actingAs($admin)->patch(route('invoices.update', $invoice), [
            'status' => InvoiceStatus::Betaald->value,
        ]);

        $this->assertSame(BudgetTerm::STATUS_PAID, $term->fresh()->status);
    }

    public function test_the_final_settlement_nets_extras_deductions_and_what_is_already_invoiced(): void
    {
        [$project, $budget] = $this->projectWithBudget();

        // Goedgekeurd meerwerk van 1.000, met tien procent staart erover.
        $budget->options()->create([
            'description' => 'Extra wastafel',
            'quantity' => 1,
            'unit' => 'pst',
            'price' => 1000,
            'status' => BudgetOption::STATUS_APPROVED,
        ]);

        ProjectDeduction::create([
            'project_id' => $project->id,
            'description' => 'Plint vervalt',
            'amount' => 200,
            'status' => ProjectDeduction::STATUS_AGREED,
        ]);

        // Een vervallen voorstel mag niet meetellen.
        ProjectDeduction::create([
            'project_id' => $project->id,
            'description' => 'Nog niet besloten',
            'amount' => 500,
            'status' => ProjectDeduction::STATUS_PROPOSED,
        ]);

        Invoice::create([
            'number' => Invoice::nextNumber(),
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'type' => InvoiceType::Termijn,
            'description' => 'Bij start',
            'date' => today(),
            'due_date' => today()->addDays(14),
            'amount' => 5500,
            'status' => InvoiceStatus::Verzonden,
        ]);

        $settlement = (new BuildFinalSettlement)->preview($project->fresh());

        $this->assertSame(11000.0, $settlement['base']);
        $this->assertSame(1100.0, $settlement['extras']);
        $this->assertSame(200.0, $settlement['deduction_total']);
        $this->assertSame(11900.0, $settlement['final']);
        $this->assertSame(5500.0, $settlement['invoiced']);
        $this->assertSame(6400.0, $settlement['balance']);
        // Vijf procent over 11.900 inclusief 21 procent btw.
        $this->assertSame(719.95, $settlement['retention']);
    }

    public function test_a_business_customer_has_no_right_to_withhold_five_percent(): void
    {
        [$project] = $this->projectWithBudget();
        $project->update(['retention_type' => 'nvt']);

        $settlement = (new BuildFinalSettlement)->preview($project->fresh());

        $this->assertSame(0.0, $settlement['retention']);
    }

    public function test_the_final_invoice_lists_every_component(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administratie]);
        [$project, $budget] = $this->projectWithBudget();

        $budget->options()->create([
            'description' => 'Extra wastafel',
            'quantity' => 1,
            'price' => 1000,
            'status' => BudgetOption::STATUS_APPROVED,
        ]);

        $this->actingAs($admin)->post(route('invoices.final', $project))->assertRedirect();

        $invoice = Invoice::firstWhere('type', InvoiceType::Eindafrekening);

        $this->assertNotNull($invoice);
        $this->assertSame(2, $invoice->lines->count());
        $this->assertStringContainsString('Aanneemsom', $invoice->lines[0]->description);
        $this->assertStringContainsString('Meerwerk', $invoice->lines[1]->description);
        // De inhouding staat op het project vastgelegd voor later vrijgeven.
        $this->assertGreaterThan(0, (float) $project->fresh()->retention_amount);

        // Een tweede keer levert geen dubbele eindafrekening op.
        $this->actingAs($admin)->post(route('invoices.final', $project))->assertSessionHas('error');
        $this->assertSame(1, Invoice::where('type', InvoiceType::Eindafrekening)->count());
    }

    public function test_the_cost_review_compares_budget_against_what_is_booked(): void
    {
        [$project, $budget] = $this->projectWithBudget();
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder, 'hourly_rate' => 50]);

        // 60 geboekte uren tegenover 50 begrote op deze bonnen: trager dan gedacht.
        WorkOrder::create([
            'number' => WorkOrder::nextNumber(),
            'project_id' => $project->id,
            'user_id' => $uitvoerder->id,
            'title' => 'Tegelwerk week 1',
            'date' => today(),
            'status' => WorkOrderStatus::Goedgekeurd,
            'hours' => 60,
            'budget_hours' => 50,
            'hourly_rate' => 50,
            'approved_at' => now(),
        ]);

        PurchaseInvoice::create([
            'number' => 'L-1001',
            'supplier' => 'Tegelgroothandel',
            'project_id' => $project->id,
            'category' => 'materiaal',
            'date' => today(),
            'due_date' => today()->addDays(30),
            'amount' => 3200,
            'status' => PurchaseInvoice::STATUS_APPROVED,
        ]);

        // Een nieuwe, nog niet goedgekeurde factuur mag niet meetellen.
        PurchaseInvoice::create([
            'number' => 'L-1002',
            'supplier' => 'Tegelgroothandel',
            'project_id' => $project->id,
            'category' => 'materiaal',
            'date' => today(),
            'due_date' => today()->addDays(30),
            'amount' => 999,
            'status' => PurchaseInvoice::STATUS_NEW,
        ]);

        $review = (new CostReviewService)->for($project->fresh());

        $this->assertNotNull($review);
        $this->assertSame(5000.0, $review['rows'][0]['budgeted']);
        $this->assertSame(3000.0, $review['rows'][0]['actual']);
        $this->assertSame(60.0, $review['hours_actual']);
        $this->assertSame(3200.0, $review['rows'][1]['actual']);

        // De urenfactor is 1,2 gemeten, maar weegt mee naar rato van de 50 van de
        // 30 uur die voor volledig gewicht nodig zijn: dus volledig.
        $this->assertSame(1.2, $review['efficiency']);

        // Arbeid verwacht: 3.000 geboekt plus de helft die nog komt maal 1,2.
        $this->assertSame(6000.0, $review['rows'][0]['expected']);
        $this->assertSame(20.0, $review['rows'][0]['variance']);
    }

    public function test_the_measured_hours_weigh_less_early_in_a_project(): void
    {
        [$project, $budget] = $this->projectWithBudget();
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder, 'hourly_rate' => 50]);

        // Maar 6 van de 100 begrote uren gemeten: dat zegt nog weinig.
        WorkOrder::create([
            'number' => WorkOrder::nextNumber(),
            'project_id' => $project->id,
            'user_id' => $uitvoerder->id,
            'title' => 'Eerste dag',
            'date' => today(),
            'status' => WorkOrderStatus::Goedgekeurd,
            'hours' => 12,
            'budget_hours' => 6,
            'hourly_rate' => 50,
            'approved_at' => now(),
        ]);

        $review = (new CostReviewService)->for($project->fresh());

        // Gemeten is factor 2, maar met 6 van de 30 benodigde uren weegt dat voor
        // een vijfde mee: 1 + (2 - 1) * 0,2 is 1,2.
        $this->assertSame(1.2, $review['efficiency']);
    }

    public function test_open_purchase_orders_count_as_commitments(): void
    {
        [$project] = $this->projectWithBudget();

        $order = PurchaseOrder::create([
            'number' => PurchaseOrder::nextNumber(),
            'project_id' => $project->id,
            'supplier' => 'Sanitairhandel',
            'category' => 'materiaal',
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'expected_on' => today()->addWeek(),
        ]);

        $order->lines()->create(['description' => 'Sanitair', 'quantity' => 1, 'unit_price' => 2400]);

        $review = (new CostReviewService)->for($project->fresh());

        // Nog niets gefactureerd, maar 2.400 besteld: dat hoort in het verwachte beeld.
        $this->assertSame(0.0, $review['rows'][1]['actual']);
        $this->assertStringContainsString('2.400 besteld', $review['rows'][1]['note']);
    }

    public function test_the_same_supplier_cannot_book_one_invoice_number_twice(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administratie]);
        [$project] = $this->projectWithBudget();

        $payload = [
            'number' => 'L-5000',
            'supplier' => 'Bouwmarkt',
            'project_id' => $project->id,
            'category' => 'materiaal',
            'date' => today()->toDateString(),
            'amount' => 500,
        ];

        $this->actingAs($admin)->post(route('purchase-invoices.store'), $payload)->assertSessionHas('success');
        $this->actingAs($admin)->post(route('purchase-invoices.store'), $payload)->assertSessionHas('error');

        $this->assertSame(1, PurchaseInvoice::count());
    }

    public function test_approving_a_purchase_invoice_books_it(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administratie]);
        [$project] = $this->projectWithBudget();

        $invoice = PurchaseInvoice::create([
            'number' => 'L-6000',
            'supplier' => 'Bouwmarkt',
            'project_id' => $project->id,
            'category' => 'materiaal',
            'date' => today(),
            'due_date' => today()->addDays(30),
            'amount' => 750,
            'status' => PurchaseInvoice::STATUS_NEW,
        ]);

        $this->assertFalse($invoice->isBooked());

        $this->actingAs($admin)->patch(route('purchase-invoices.update', $invoice), [
            'status' => PurchaseInvoice::STATUS_APPROVED,
        ])->assertSessionHas('success');

        $invoice->refresh();

        $this->assertTrue($invoice->isBooked());
        $this->assertSame($admin->id, $invoice->approved_by);
    }

    public function test_the_finance_overview_opens_for_management(): void
    {
        $directie = User::factory()->create(['role' => UserRole::Directie]);
        $this->projectWithBudget();

        $this->actingAs($directie)->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Werkvoorraad')
            ->assertSee('Cashflow');

        $this->actingAs($directie)->get(route('cost-reviews.index'))
            ->assertOk()
            ->assertSee('Nacalculatie');
    }

    public function test_a_craftsman_cannot_reach_the_money_module(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($uitvoerder)->get(route('invoices.index'))->assertForbidden();
    }
}
