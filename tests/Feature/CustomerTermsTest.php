<?php

namespace Tests\Feature;

use App\Actions\ApplyFrameworkPreset;
use App\Enums\CalculationLineType;
use App\Enums\CustomerType;
use App\Enums\UserRole;
use App\Models\Budget;
use App\Models\Customer;
use App\Models\FrameworkAgreement;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\PriceItem;
use App\Models\Project;
use App\Models\User;
use App\Support\Margin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ronde 28 tot en met 34: particulier en zakelijk, de betaaltermijn per
 * opdrachtgever en de raamovereenkomst met vaste prijzen.
 */
class CustomerTermsTest extends TestCase
{
    use RefreshDatabase;

    private function businessWithAgreement(): array
    {
        $customer = Customer::factory()->create(['name' => 'Unica Installatie', 'type' => CustomerType::Zakelijk, 'payment_days' => 30]);
        $agreement = $customer->frameworkAgreements()->create([
            'number' => FrameworkAgreement::nextNumber(),
            'starts_on' => today()->subMonth(),
            'ends_on' => today()->addMonths(11),
            'payment_days' => 30,
        ]);

        $wand = PriceItem::factory()->create(['name' => 'Wanden glad stucwerk', 'type' => CalculationLineType::Onderaannemer, 'unit' => 'm2', 'unit_price' => 20]);
        $plafond = PriceItem::factory()->create(['name' => 'Plafond glad stucwerk', 'type' => CalculationLineType::Onderaannemer, 'unit' => 'm2', 'unit_price' => 30]);

        $agreement->prices()->create(['price_item_id' => $wand->id, 'description' => $wand->name, 'unit' => 'm2', 'fixed_price' => 25, 'position' => 1]);
        $agreement->prices()->create(['price_item_id' => $plafond->id, 'description' => $plafond->name, 'unit' => 'm2', 'fixed_price' => 33, 'position' => 2]);

        return [$customer->fresh(), $agreement->fresh()];
    }

    public function test_every_customer_gets_a_number_and_starts_as_private_with_fourteen_days(): void
    {
        $customer = Customer::factory()->create()->fresh();

        $this->assertSame(sprintf('K-%04d', $customer->id), $customer->number);
        $this->assertSame(CustomerType::Particulier, $customer->type);
        $this->assertSame(14, $customer->payment_days);
        $this->assertTrue($customer->reminders);
    }

    public function test_the_customer_list_filters_on_private_and_business(): void
    {
        $admin = User::factory()->create();
        Customer::factory()->create(['name' => 'Familie Jansen']);
        [$unica] = $this->businessWithAgreement();

        $this->actingAs($admin)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Familie Jansen')
            ->assertSee('Unica Installatie')
            ->assertSee('Raamovereenkomst')
            ->assertSee($unica->number);

        $this->actingAs($admin)->get(route('customers.index', ['soort' => 'zakelijk']))
            ->assertSee('Unica Installatie')
            ->assertDontSee('Familie Jansen');

        // Zoeken op klantnummer werkt ook.
        $this->actingAs($admin)->get(route('customers.index', ['q' => $unica->number]))
            ->assertSee('Unica Installatie')
            ->assertDontSee('Familie Jansen');
    }

    public function test_switching_to_business_moves_the_payment_term_to_thirty_days(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->patch(route('customers.update', $customer), [
            'name' => $customer->name,
            'type' => 'zakelijk',
            'payment_days' => '',
            'contact_name' => 'Rob de Inkoper',
        ])->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame(CustomerType::Zakelijk, $customer->type);
        $this->assertSame(30, $customer->payment_days);
        // Het vinkje stond niet in het formulier, dus geen herinnering meer.
        $this->assertFalse($customer->reminders);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'customer', 'action' => 'klantgegevens bijgewerkt']);

        // Een eigen afspraak gaat boven de standaard.
        $this->actingAs($admin)->patch(route('customers.update', $customer), [
            'name' => $customer->name, 'type' => 'zakelijk', 'payment_days' => 45, 'reminders' => 1,
        ]);
        $this->assertSame(45, $customer->fresh()->payment_days);
        $this->assertTrue($customer->fresh()->reminders);
    }

    public function test_an_invoice_falls_due_after_the_term_of_the_customer(): void
    {
        $private = Customer::factory()->create();
        $business = Customer::factory()->create(['type' => CustomerType::Zakelijk, 'payment_days' => 30]);

        $this->assertTrue(Invoice::dueDateFor($private)->isSameDay(today()->addDays(14)));
        $this->assertTrue(Invoice::dueDateFor($business)->isSameDay(today()->addDays(30)));
        // Zonder klant geldt de termijn die Renovion zelf aanhoudt.
        $this->assertTrue(Invoice::dueDateFor(null)->isSameDay(today()->addDays(Invoice::PAYMENT_DAYS)));
    }

    public function test_a_term_invoice_uses_the_payment_term_of_a_business_customer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administratie]);
        $customer = Customer::factory()->create(['type' => CustomerType::Zakelijk, 'payment_days' => 30]);
        $project = Project::factory()->create(['customer_id' => $customer->id]);

        $budget = Budget::create([
            'number' => Budget::nextNumber(), 'project_id' => $project->id, 'customer_id' => $customer->id,
            'name' => 'Begroting', 'status' => Budget::STATUS_FINAL, 'hourly_rate' => 50, 'vat_pct' => 21,
        ]);
        $budget->chapters()->create(['code' => '41', 'name' => 'Werk'])
            ->paragraphs()->create(['code' => '.01', 'name' => 'Werk'])
            ->lines()->create(['description' => 'Werk', 'quantity' => 1, 'unit' => 'pst', 'material' => 1000]);
        $term = $budget->terms()->create(['description' => 'Na oplevering', 'percentage' => 100, 'position' => 0]);

        $this->actingAs($admin)->post(route('invoices.term', $term))->assertRedirect();

        $this->assertTrue(Invoice::firstWhere('budget_term_id', $term->id)->due_date->isSameDay(today()->addDays(30)));
    }

    public function test_a_framework_agreement_belongs_to_a_business_customer(): void
    {
        $admin = User::factory()->create();
        $private = Customer::factory()->create();

        $this->actingAs($admin)->post(route('framework-agreements.store', $private), [
            'starts_on' => today()->toDateString(), 'payment_days' => 30,
        ])->assertSessionHas('error');
        $this->assertDatabaseCount('framework_agreements', 0);

        $business = Customer::factory()->create(['type' => CustomerType::Zakelijk, 'payment_days' => 14]);

        $this->actingAs($admin)->post(route('framework-agreements.store', $business), [
            'starts_on' => today()->toDateString(), 'ends_on' => today()->addYear()->toDateString(), 'payment_days' => 30,
        ])->assertSessionHas('success');

        $agreement = $business->frameworkAgreements()->first();
        $this->assertTrue($agreement->isRunning());
        $this->assertTrue($agreement->preset_active);
        // Vaste prijzen zijn verkoopprijzen: de opslag begint op nul.
        $this->assertSame(0.0, (float) $agreement->overhead_pct);
        // De afspraak over betalen geldt meteen voor de facturen van deze klant.
        $this->assertSame(30, $business->fresh()->payment_days);
    }

    public function test_fixed_prices_show_their_margin_against_the_price_list(): void
    {
        $admin = User::factory()->create();
        [$customer, $agreement] = $this->businessWithAgreement();
        $prices = $agreement->prices;

        // 25 verkoop, 20 kost: 20 procent, boven de baseline van 17.
        $this->assertSame(20.0, $prices[0]->marginPct());
        $this->assertSame(Margin::GREEN, Margin::level($prices[0]->marginPct()));
        // 33 verkoop, 30 kost: 9,1 procent, onder de bodem van 12.
        $this->assertSame(9.1, $prices[1]->marginPct());
        $this->assertSame(Margin::RED, Margin::level($prices[1]->marginPct()));
        $this->assertSame(Margin::AMBER, Margin::level(15.0));

        $this->actingAs($admin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($agreement->number)
            ->assertSee('Wanden glad stucwerk')
            ->assertSee('9,1%');

        // Een bedrag met komma en valutateken mag gewoon.
        $item = PriceItem::factory()->create(['unit_price' => 10]);
        $this->actingAs($admin)->post(route('framework-prices.store', $agreement), [
            'price_item_id' => $item->id, 'fixed_price' => 'EUR 12,50',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('framework_prices', ['price_item_id' => $item->id, 'fixed_price' => 12.50]);
    }

    public function test_a_new_budget_for_this_customer_starts_from_the_fixed_prices(): void
    {
        $calculator = User::factory()->create(['role' => UserRole::Calculator]);
        [$customer, $agreement] = $this->businessWithAgreement();
        $lead = Lead::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($calculator)->post(route('budgets.store'), [
            'name' => 'Stucwerk Arnhemseweg',
            'lead_id' => $lead->id,
        ])->assertRedirect();

        $budget = Budget::with(['chapters.paragraphs.lines', 'markups', 'terms'])->firstOrFail();

        // Alleen het hoofdstuk met vaste prijzen, niet het standaardskelet.
        $this->assertCount(1, $budget->chapters);
        $this->assertSame(ApplyFrameworkPreset::CHAPTER_CODE, $budget->chapters[0]->code);

        $lines = $budget->chapters[0]->paragraphs[0]->lines;
        $this->assertCount(2, $lines);
        $this->assertSame(0.0, (float) $lines[0]->quantity);
        $this->assertSame(25.0, (float) $lines[0]->subcontracting);
        $this->assertSame(20.0, $lines[0]->frameworkMarginPct());

        // Opslag volgens de overeenkomst, en in één keer afrekenen na oplevering.
        $this->assertSame(0.0, (float) $budget->markups->sum('percentage'));
        $this->assertCount(1, $budget->terms);
        $this->assertSame(100.0, (float) $budget->terms[0]->percentage);
    }

    public function test_the_preset_can_be_applied_to_an_existing_budget_without_doubling_lines(): void
    {
        $calculator = User::factory()->create(['role' => UserRole::Calculator]);
        [$customer, $agreement] = $this->businessWithAgreement();

        $budget = Budget::create([
            'number' => Budget::nextNumber(), 'customer_id' => $customer->id,
            'name' => 'Bestaande begroting', 'status' => Budget::STATUS_DRAFT, 'hourly_rate' => 55, 'vat_pct' => 21,
        ]);

        $this->actingAs($calculator)->get(route('budgets.show', $budget))->assertOk()->assertSee('Preset toepassen');

        $this->actingAs($calculator)->post(route('budgets.preset', $budget))->assertSessionHas('success');
        $this->actingAs($calculator)->post(route('budgets.preset', $budget))->assertSessionHas('success');

        $this->assertSame(2, $budget->chapters()->firstOrFail()->paragraphs()->firstOrFail()->lines()->count());

        // Een definitieve begroting staat op slot.
        $budget->update(['status' => Budget::STATUS_FINAL]);
        $this->actingAs($calculator)->post(route('budgets.preset', $budget))->assertSessionHas('error');
    }

    public function test_the_preset_stays_off_when_the_agreement_says_so_or_has_ended(): void
    {
        $calculator = User::factory()->create(['role' => UserRole::Calculator]);
        [$customer, $agreement] = $this->businessWithAgreement();
        $lead = Lead::factory()->create(['customer_id' => $customer->id]);

        $agreement->update(['preset_active' => false]);
        $this->actingAs($calculator)->post(route('budgets.store'), ['name' => 'Badkamer', 'lead_id' => $lead->id]);
        $this->assertNotSame(ApplyFrameworkPreset::CHAPTER_CODE, Budget::latest('id')->first()->chapters()->first()->code);

        $agreement->update(['preset_active' => true, 'ends_on' => today()->subDay()]);
        $this->assertNull($customer->fresh()->activeFramework());
    }

    public function test_the_price_list_can_be_grouped(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('price-items.store'), [
            'name' => 'Wand sausklaar', 'type' => 'onderaannemer', 'unit' => 'm2', 'unit_price' => 18.5, 'group' => 'Wandwerk',
        ])->assertRedirect();
        PriceItem::factory()->create(['name' => 'Plafond sausklaar', 'group' => 'Plafonds']);

        $this->actingAs($admin)->get(route('price-items.index', ['groep' => 'Wandwerk']))
            ->assertOk()
            ->assertSee('Wand sausklaar')
            ->assertDontSee('Plafond sausklaar');
    }

    public function test_an_uitvoerder_cannot_change_customers_or_agreements(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        [$customer, $agreement] = $this->businessWithAgreement();

        $this->actingAs($uitvoerder)->patch(route('customers.update', $customer), ['name' => 'X'])->assertForbidden();
        $this->actingAs($uitvoerder)->delete(route('framework-agreements.destroy', $agreement))->assertForbidden();
    }
}
