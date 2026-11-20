<?php

namespace Tests\Feature;

use App\Enums\CustomerType;
use App\Enums\InvoiceType;
use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Budget;
use App\Models\Customer;
use App\Models\ExtraWork;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use App\Support\InvoicePlan;
use App\Support\RegieStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Urenstaat (aangenomen of regie), meerwerk als eigen opdracht en het
 * factuurplan.
 */
class HoursAndExtraWorkTest extends TestCase
{
    use RefreshDatabase;

    private function project(array $attributes = []): Project
    {
        return Project::factory()->create([
            'customer_id' => Customer::factory()->create(['type' => CustomerType::Zakelijk, 'payment_days' => 30]),
            'name' => 'Stucwerk kantoor Unica',
            ...$attributes,
        ]);
    }

    public function test_hours_on_fixed_price_work_are_for_information_only(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = $this->project();

        $this->actingAs($leider)->post(route('hours.store', $project), [
            'date' => today()->toDateString(), 'hours' => '7,5', 'description' => 'Wanden gestuct',
        ])->assertSessionHas('success');

        $statement = RegieStatement::for($project->fresh());

        $this->assertSame(7.5, $statement->hours());
        $this->assertFalse($statement->isRegie());
        $this->assertFalse($statement->canInvoice());
        $this->assertStringContainsString('aangenomen werk', $statement->blocker());

        // Factureren is aan wie de facturatie doet, en bij aangenomen werk kan het sowieso niet.
        $this->actingAs($leider)->post(route('hours.invoice', $project))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('hours.invoice', $project))->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);

        $this->actingAs($leider)->get(route('projects.show', [$project, 'tab' => 'uren']))
            ->assertOk()
            ->assertSee('Wanden gestuct')
            ->assertSee('ter informatie');
    }

    public function test_regie_is_hours_times_rate_plus_materials(): void
    {
        $admin = User::factory()->create();
        $project = $this->project();

        $this->actingAs($admin)->patch(route('hours.contract', $project), [
            'contract_form' => 'regie', 'regie_hourly_rate' => '60,00',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('hours.store', $project), ['date' => today()->toDateString(), 'hours' => 8]);
        $this->actingAs($admin)->post(route('hours.store', $project), ['date' => today()->subDay()->toDateString(), 'hours' => 4]);
        $this->actingAs($admin)->post(route('regie-materials.store', $project), [
            'description' => 'Gipspleister', 'quantity' => '10', 'unit' => 'zak', 'price' => '12,50',
        ])->assertSessionHas('success');

        $statement = RegieStatement::for($project->fresh());

        // 12 uur maal 60 is 720, plus 125 materiaal.
        $this->assertSame(720.0, $statement->labour($statement->hours()));
        $this->assertSame(125.0, $statement->materialsTotal());
        $this->assertSame(845.0, $statement->total());
        $this->assertSame(845.0, $statement->toInvoice());
    }

    public function test_with_a_day_rate_we_bill_days_of_eight_hours(): void
    {
        $project = $this->project(['contract_form' => 'regie', 'regie_hourly_rate' => 60, 'regie_day_rate' => 400]);
        $project->timeEntries()->create(['date' => today(), 'hours' => 12]);

        $statement = RegieStatement::for($project->fresh());

        $this->assertTrue($statement->perDay());
        $this->assertSame(1.5, $statement->units(12));
        $this->assertSame(600.0, $statement->total());
    }

    public function test_regie_is_invoiced_only_after_signing_and_never_twice(): void
    {
        $admin = User::factory()->create(['name' => 'Rob Directie']);
        $project = $this->project(['contract_form' => 'regie', 'regie_hourly_rate' => 50]);
        $project->timeEntries()->create(['date' => today(), 'hours' => 10, 'user_id' => $admin->id]);

        // Niet afgetekend: nog niet factureren.
        $this->actingAs($admin)->post(route('hours.invoice', $project))->assertSessionHas('error');

        $this->actingAs($admin)->post(route('hours.sign', $project))->assertSessionHas('success');
        $project->refresh();
        $this->assertSame(10.0, (float) $project->hours_signed_total);
        $this->assertSame($admin->id, $project->hours_signed_by);

        // Na het aftekenen komt er een uur bij: opnieuw aftekenen.
        $project->timeEntries()->create(['date' => today(), 'hours' => 2, 'user_id' => $admin->id]);
        $statement = RegieStatement::for($project->fresh());
        $this->assertTrue($statement->needsResign());
        $this->actingAs($admin)->post(route('hours.invoice', $project))->assertSessionHas('error');

        $this->actingAs($admin)->post(route('hours.sign', $project));
        $this->actingAs($admin)->post(route('hours.invoice', $project))->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame(InvoiceType::Regie, $invoice->type);
        $this->assertSame(600.0, (float) $invoice->amount);
        // Zakelijke klant: dertig dagen.
        $this->assertTrue($invoice->due_date->isSameDay(today()->addDays(30)));
        $this->assertSame(12.0, (float) $invoice->lines->first()->quantity);

        // Alles staat nu op een factuur: er valt niets meer af te rekenen.
        $statement = RegieStatement::for($project->fresh());
        $this->assertSame(0.0, $statement->toInvoice());
        $this->assertSame(600.0, $statement->invoiced());
        $this->actingAs($admin)->post(route('hours.invoice', $project))->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 1);

        // Gefactureerde uren kunnen niet meer weg.
        $this->actingAs($admin)->delete(route('hours.destroy', $project->timeEntries()->first()))->assertSessionHas('error');
    }

    public function test_only_directors_and_project_leaders_sign_off_hours(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = $this->project();
        $project->timeEntries()->create(['date' => today(), 'hours' => 4]);

        $this->actingAs($sales)->post(route('hours.sign', $project))->assertForbidden();
        $this->actingAs($leider)->post(route('hours.sign', $project))->assertSessionHas('success');
    }

    public function test_an_uitvoerder_books_only_his_own_hours_on_his_own_projects(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $collega = User::factory()->uitvoerder()->create();
        $project = $this->project();
        $ander = $this->project();
        $project->craftsmen()->attach($uitvoerder->id);

        $this->actingAs($uitvoerder)->post(route('hours.store', $project), [
            'date' => today()->toDateString(), 'hours' => 6, 'user_id' => $collega->id,
        ])->assertSessionHas('success');

        // Ook al stuurt hij een collega mee, de uren komen op zijn eigen naam.
        $this->assertSame($uitvoerder->id, $project->timeEntries()->first()->user_id);

        $this->actingAs($uitvoerder)->post(route('hours.store', $ander), ['date' => today()->toDateString(), 'hours' => 6])->assertForbidden();
        $this->actingAs($uitvoerder)->post(route('hours.store', $project), ['date' => today()->addDay()->toDateString(), 'hours' => 6])
            ->assertSessionHasErrors('date');

        // Tarief en materiaal zijn niet aan hem.
        $this->actingAs($uitvoerder)->patch(route('hours.contract', $project), ['contract_form' => 'regie'])->assertForbidden();
    }

    public function test_the_office_overview_and_the_printout_show_the_statement(): void
    {
        $admin = User::factory()->create();
        $project = $this->project(['contract_form' => 'regie', 'regie_hourly_rate' => 50]);
        $project->timeEntries()->create(['date' => today(), 'hours' => 10, 'description' => 'Plafonds']);

        $this->actingAs($admin)->get(route('hours.index'))
            ->assertOk()
            ->assertSee('Stucwerk kantoor Unica')
            ->assertSee('Nog niet');

        $this->actingAs($admin)->get(route('hours.index', ['vorm' => 'aangenomen']))->assertDontSee('Stucwerk kantoor Unica');

        $this->actingAs($admin)->get(route('hours.print', $project))
            ->assertOk()
            ->assertSee('Urenstaat')
            ->assertSee('Plafonds')
            ->assertSee('Voor akkoord opdrachtgever');
    }

    public function test_extra_work_gets_a_number_under_the_project(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = $this->project();

        $this->actingAs($leider)->post(route('extra-works.store', $project), [
            'description' => 'Extra wandcontactdozen', 'amount' => 'EUR 1.250,00',
        ])->assertSessionHas('success');
        $this->actingAs($leider)->post(route('extra-works.store', $project), ['description' => 'Tweede laag stuc', 'amount' => 800]);

        $numbers = $project->extraWorks()->pluck('number')->all();

        $this->assertSame([$project->number().'-MW01', $project->number().'-MW02'], $numbers);
        $this->assertSame(1250.0, (float) $project->extraWorks()->first()->amount);
    }

    public function test_extra_work_is_only_invoiced_after_the_customer_agrees_online(): void
    {
        $admin = User::factory()->create();
        $project = $this->project();
        $extra = $project->extraWorks()->create([
            'number' => ExtraWork::nextNumberFor($project), 'description' => 'Extra wandcontactdozen', 'amount' => 1250,
            'status' => ExtraWork::STATUS_DRAFT, 'public_token' => ExtraWork::newToken(),
        ]);

        // Een concept is nog niet voor de klant.
        $this->get(route('extra-works.public', $extra->public_token))->assertNotFound();
        // En zonder akkoord geen factuur.
        $this->actingAs($admin)->post(route('extra-works.invoice', $extra))->assertSessionHas('error');

        $this->actingAs($admin)->post(route('extra-works.send', $extra))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('extra-works.invoice', $extra))->assertSessionHas('error');
        $this->post('/logout');

        $this->get(route('extra-works.public', $extra->public_token))
            ->assertOk()
            ->assertSee('Extra wandcontactdozen')
            ->assertSee('€ 1.250,00')
            ->assertSee('Akkoord geven');

        // Zonder vinkje geen akkoord.
        $this->post(route('extra-works.accept', $extra->public_token), ['name' => 'Rob de Inkoper'])->assertSessionHasErrors('agree');

        $this->post(route('extra-works.accept', $extra->public_token), ['name' => 'Rob de Inkoper', 'agree' => 1])
            ->assertSessionHas('success');

        $extra->refresh();
        $this->assertSame(ExtraWork::STATUS_APPROVED, $extra->status);
        $this->assertSame('Rob de Inkoper', $extra->approved_name);
        $this->assertNotNull($extra->approved_ip);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'extra_work', 'action' => 'meerwerk akkoord', 'source' => 'website']);
        $this->assertDatabaseHas('chat_messages', ['body' => "Meerwerk {$extra->number} heeft akkoord van Rob de Inkoper: Extra wandcontactdozen."]);

        // Twee keer akkoord geven kan niet.
        $this->post(route('extra-works.accept', $extra->public_token), ['name' => 'Iemand anders', 'agree' => 1])->assertSessionHas('error');

        $this->actingAs($admin)->post(route('extra-works.invoice', $extra))->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame(InvoiceType::Meerwerk, $invoice->type);
        $this->assertSame(1250.0, (float) $invoice->amount);
        $this->assertSame(ExtraWork::STATUS_INVOICED, $extra->fresh()->status);

        // Niet twee keer factureren, en een verzonden meerwerk kan niet meer weg.
        $this->actingAs($admin)->post(route('extra-works.invoice', $extra))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('extra-works.destroy', $extra))->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_approval_given_outside_the_system_is_recorded_with_a_name(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = $this->project();
        $extra = $project->extraWorks()->create([
            'number' => ExtraWork::nextNumberFor($project), 'description' => 'Extra werk', 'amount' => 500,
            'status' => ExtraWork::STATUS_SENT, 'public_token' => ExtraWork::newToken(), 'sent_at' => now(),
        ]);

        $this->actingAs($leider)->post(route('extra-works.approve', $extra))->assertSessionHasErrors('approved_name');
        $this->actingAs($leider)->post(route('extra-works.approve', $extra), ['approved_name' => 'Dhr. Jansen, per mail'])
            ->assertSessionHas('success');

        $this->assertTrue($extra->fresh()->isApproved());
        $this->assertNull($extra->fresh()->approved_ip);

        $this->actingAs($leider)->get(route('projects.show', [$project, 'tab' => 'meerwerk']))
            ->assertOk()
            ->assertSee($extra->number)
            ->assertSee('Dhr. Jansen, per mail');
    }

    public function test_the_invoice_plan_comes_from_the_budget_terms(): void
    {
        $project = $this->project();
        $budget = Budget::create([
            'number' => Budget::nextNumber(), 'project_id' => $project->id, 'customer_id' => $project->customer_id,
            'name' => 'Begroting', 'status' => Budget::STATUS_FINAL, 'hourly_rate' => 50, 'vat_pct' => 21,
        ]);
        $budget->chapters()->create(['code' => '41', 'name' => 'Werk'])
            ->paragraphs()->create(['code' => '.01', 'name' => 'Werk'])
            ->lines()->create(['description' => 'Werk', 'quantity' => 1, 'unit' => 'pst', 'material' => 10000]);
        $budget->terms()->create(['description' => 'Bij opdracht', 'percentage' => 40, 'position' => 0]);
        $budget->terms()->create(['description' => 'Na oplevering', 'percentage' => 50, 'position' => 1]);

        $plan = InvoicePlan::for($project);

        $this->assertSame(InvoicePlan::SOURCE_BUDGET, $plan->source);
        $this->assertSame([4000.0, 5000.0], $plan->rows->pluck('amount')->all());
        $this->assertTrue($plan->invoiceable);
        // Negentig procent: er mist geld in het plan.
        $this->assertSame(90.0, $plan->coverage());

        $admin = User::factory()->create();
        $this->actingAs($admin)->get(route('projects.show', [$project, 'tab' => 'meerwerk']))
            ->assertOk()
            ->assertSee('Factuur maken')
            ->assertSee('De termijnen tellen op tot 90 procent');

        $this->actingAs($admin)->post(route('invoices.term', $budget->terms->first()));
        $this->assertSame(4000.0, InvoicePlan::for($project)->invoicedAmount());
    }

    public function test_without_a_budget_the_plan_comes_from_the_signed_quote(): void
    {
        $project = $this->project();
        $this->assertTrue(InvoicePlan::for($project)->isEmpty());

        $quote = Quote::factory()->create([
            'customer_id' => $project->customer_id, 'project_id' => $project->id,
            'status' => QuoteStatus::Akkoord, 'subtotal' => 20000, 'total' => 24200,
        ]);

        $plan = InvoicePlan::for($project->fresh());

        $this->assertSame(InvoicePlan::SOURCE_QUOTE, $plan->source);
        $this->assertSame($quote->number, $plan->reference);
        $this->assertSame(20000.0, $plan->base);
        $this->assertSame(100.0, $plan->coverage());
        // Zonder vastgelegde termijnen valt er nog niets te factureren.
        $this->assertFalse($plan->invoiceable);
    }
}
