<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\UserRole;
use App\Models\Budget;
use App\Models\BudgetTerm;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Facturatie als eigen scherm in de app: lijst, termijn factureren, versturen, betaald melden en verwijderen. */
class AppInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function token(UserRole $role = UserRole::Administratie): string
    {
        return User::factory()->create(['role' => $role])->createToken('test')->plainTextToken;
    }

    /** Een project met een definitieve begroting (aanneemsom 11.000) en twee termijnen van vijftig procent. */
    private function budget(): Budget
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory()->create(['name' => 'Fam. Jansen']), 'name' => 'Badkamer Jansen', 'value' => 11000]);

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
        $paragraph = $budget->chapters()->create(['code' => '41', 'name' => 'Tegelwerk'])
            ->paragraphs()->create(['code' => '.01', 'name' => 'Wandtegels']);
        $paragraph->lines()->create(['description' => 'Tegelwerk', 'quantity' => 100, 'unit' => 'uur', 'norm_hours' => 1]);
        $paragraph->lines()->create(['description' => 'Tegels', 'quantity' => 1, 'unit' => 'pst', 'material' => 5000]);
        $budget->markups()->create(['name' => 'Algemene kosten', 'percentage' => 10, 'position' => 0]);
        $budget->terms()->create(['description' => 'Bij start', 'percentage' => 50, 'position' => 0]);
        $budget->terms()->create(['description' => 'Bij oplevering', 'percentage' => 50, 'position' => 1]);

        return $budget->fresh();
    }

    private function sentInvoice(array $attributes = []): Invoice
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory()->create(['name' => 'Dhr. De Vries'])]);

        return Invoice::create([
            'number' => Invoice::nextNumber(),
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'type' => InvoiceType::Meerwerk,
            'description' => 'Meerwerk keuken',
            'date' => today()->subDays(30),
            'due_date' => today()->subDays(16),
            'amount' => 1000,
            'vat_pct' => 21,
            'status' => InvoiceStatus::Verzonden,
            'sent_at' => now()->subDays(30),
            ...$attributes,
        ]);
    }

    public function test_the_list_shows_the_figures_terms_ready_to_invoice_and_overdue_invoices(): void
    {
        $budget = $this->budget();
        $invoice = $this->sentInvoice();

        $this->withToken($this->token())->getJson(route('api.app.invoices.index'))->assertOk()
            ->assertJsonPath('kpis.outstanding', 1210)
            ->assertJsonPath('kpis.overdue', 1210)
            ->assertJsonPath('kpis.ready', 11000)
            ->assertJsonPath('kpis.ready_count', 2)
            ->assertJsonPath('ready.0.term', $budget->terms->first()->id)
            ->assertJsonPath('ready.0.sub', 'Badkamer Jansen, 50% van de aanneemsom')
            ->assertJsonPath('filters.*.value', ['', 'verzonden'])
            ->assertJsonPath('invoices.0.number', $invoice->number)
            ->assertJsonPath('invoices.0.overdue', true)
            ->assertJsonPath('invoices.0.due', '16 dagen over de vervaldatum')
            ->assertJsonPath('invoices.0.status.classes', 'bg-steel-100 text-steel-800')
            ->assertJsonPath('invoices.0.path', route('invoices.show', $invoice, false));

        $this->withToken($this->token())->getJson(route('api.app.invoices.index', ['status' => 'betaald']))->assertOk()
            ->assertJsonPath('invoices', [])
            ->assertJsonPath('filters.*.value', ['', 'verzonden', 'betaald']);
    }

    public function test_a_term_is_invoiced_sent_and_paid_from_the_app(): void
    {
        $term = $this->budget()->terms->first();
        $token = $this->token();

        $response = $this->withToken($token)->postJson(route('api.app.invoices.term', $term))->assertOk();
        $invoice = Invoice::firstWhere('budget_term_id', $term->id);
        $this->assertSame(route('invoices.show', $invoice, false), $response->json('path'));

        $this->withToken($token)->postJson(route('api.app.invoices.term', $term))
            ->assertUnprocessable()->assertJsonPath('message', 'Deze termijn is al gefactureerd.');

        $this->withToken($token)->getJson(route('api.app.invoices.show', $invoice))->assertOk()
            ->assertJsonPath('status.value', 'concept')
            ->assertJsonPath('can', ['send' => true, 'paid' => false, 'delete' => true])
            ->assertJsonPath('lines.0.quantity', '1 pst')
            ->assertJsonPath('subtotal', 5500)
            ->assertJsonPath('vat_label', 'Btw 21%')
            ->assertJsonPath('total', 6655)
            ->assertJsonPath('term.budget', $term->budget->number);

        $this->withToken($token)->patchJson(route('api.app.invoices.update', $invoice), ['status' => 'verzonden'])
            ->assertOk()->assertJsonPath('label', 'Verzonden');
        $this->assertNotNull($invoice->fresh()->sent_at);

        $this->withToken($token)->patchJson(route('api.app.invoices.update', $invoice), ['status' => 'betaald'])->assertOk();
        $this->assertSame(BudgetTerm::STATUS_PAID, $term->fresh()->status);
        $this->withToken($token)->getJson(route('api.app.invoices.show', $invoice))->assertOk()
            ->assertJsonPath('can', ['send' => false, 'paid' => false, 'delete' => false])
            ->assertJsonPath('dates.2.label', 'Betaald op');
    }

    public function test_only_a_concept_can_be_deleted_and_the_term_comes_back(): void
    {
        $term = $this->budget()->terms->first();
        $token = $this->token();
        $this->withToken($token)->postJson(route('api.app.invoices.term', $term))->assertOk();
        $invoice = Invoice::firstWhere('budget_term_id', $term->id);

        $this->withToken($token)->deleteJson(route('api.app.invoices.destroy', $invoice))
            ->assertOk()->assertJsonPath('message', "Concept {$invoice->number} verwijderd.");
        $this->assertModelMissing($invoice);
        $this->assertSame(BudgetTerm::STATUS_PLANNED, $term->fresh()->status);

        $sent = $this->sentInvoice();
        $this->withToken($token)->deleteJson(route('api.app.invoices.destroy', $sent))->assertUnprocessable();
        $this->assertModelExists($sent);
    }

    public function test_executors_cannot_open_invoices(): void
    {
        $invoice = $this->sentInvoice();
        $token = $this->token(UserRole::Uitvoerder);

        $this->withToken($token)->getJson(route('api.app.invoices.index'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.invoices.show', $invoice))->assertForbidden();
        $this->withToken($token)->patchJson(route('api.app.invoices.update', $invoice), ['status' => 'betaald'])->assertForbidden();
    }
}
