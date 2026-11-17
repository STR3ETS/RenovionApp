<?php

namespace Tests\Feature;

use App\Enums\LeadQualification;
use App\Enums\LeadStatus;
use App\Enums\PhaseStatus;
use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Budget;
use App\Models\Calculation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * De formulieren in de app (mobile/app/screens/*formulier.*): keuzelijsten en huidige waarden uit
 * Api\App\FormController, opslaan via dezelfde controllers als de website met {id, path} terug en
 * validatiefouten per veld.
 */
class AppFormsTest extends TestCase
{
    use RefreshDatabase;

    private function token(UserRole $role = UserRole::Admin): string
    {
        return User::factory()->create(['role' => $role])->createToken('test')->plainTextToken;
    }

    public function test_a_lead_is_created_for_a_new_customer_and_errors_come_back_per_field(): void
    {
        $customer = Customer::factory()->create(['name' => 'Fam. Bakker', 'city' => 'Ede']);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.forms.leads.create', ['klant' => $customer->id]))->assertOk()
            ->assertJsonPath('customer_id', $customer->id)
            ->assertJsonPath('services.0', 'Complete renovatie')
            ->assertJsonFragment(['id' => $customer->id, 'name' => 'Fam. Bakker (Ede)']);

        $this->withToken($token)->postJson(route('api.app.forms.leads.store'), ['email' => 'geen-mail'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);

        // Een datum zonder actie gaf "Volgende actie: - (20 nov)".
        $this->withToken($token)->postJson(route('api.app.forms.leads.store'), ['name' => 'Familie Jansen', 'next_action_at' => '2026-11-20T10:00'])
            ->assertJsonValidationErrors(['next_action']);

        $response = $this->withToken($token)->postJson(route('api.app.forms.leads.store'), [
            'name' => 'Familie Jansen',
            'phone' => '06 12345678',
            'city' => 'Arnhem',
            'service' => 'Badkamerrenovatie',
            'value' => '15000',
            'next_action' => 'Terugbellen',
            'next_action_at' => '2026-11-20T10:00',
        ])->assertCreated();

        $lead = Lead::latest('id')->first();
        $response->assertJsonPath('id', $lead->id)->assertJsonPath('path', route('leads.show', $lead, false));
        $this->assertSame('Familie Jansen', $lead->customer->name);
        $this->assertSame(LeadStatus::Nieuw, $lead->status);
        $this->assertSame('Badkamerrenovatie', $lead->service);
    }

    public function test_a_lead_for_an_existing_customer_and_editing_it_updates_customer_and_lead(): void
    {
        $customer = Customer::factory()->create(['phone' => null]);
        $token = $this->token();

        $this->withToken($token)->postJson(route('api.app.forms.leads.store'), ['customer_id' => $customer->id, 'service' => 'Stucwerk'])->assertCreated();
        $lead = $customer->leads()->first();

        $this->withToken($token)->getJson(route('api.app.forms.leads.edit', $lead))->assertOk()
            ->assertJsonPath('phone_missing', true)
            ->assertJsonPath('values.service', 'Stucwerk')
            ->assertJsonPath('values.qualification', $lead->qualification->value)
            ->assertJsonPath('cancel', route('leads.show', $lead, false));

        $this->withToken($token)->patchJson(route('api.app.forms.leads.update', $lead), [
            'name' => $customer->name, 'email' => '', 'phone' => '0612345678', 'address' => '', 'postal_code' => '', 'city' => '',
            'service' => 'Stucwerk', 'value' => '', 'qualification' => LeadQualification::Heet->value, 'desired_start' => 'voorjaar',
            'description' => '', 'assigned_to' => '', 'last_contact_at' => '', 'next_action' => '', 'next_action_at' => '',
        ])->assertOk()->assertJsonPath('path', route('leads.show', $lead, false));

        $this->assertSame('0612345678', $customer->fresh()->phone);
        $this->assertSame(LeadQualification::Heet, $lead->fresh()->qualification);

        $this->withToken($token)->patchJson(route('api.app.forms.leads.update', $lead), ['name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_a_project_is_created_and_edited_with_craftsmen_phase_and_cover_photo(): void
    {
        Storage::fake();
        $customer = Customer::factory()->create();
        $craftsman = User::factory()->uitvoerder()->create();
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.forms.projects.create'))->assertOk()
            ->assertJsonPath('statuses.0.value', 'voorbereiding')
            ->assertJsonFragment(['id' => $craftsman->id, 'name' => $craftsman->name]);

        $this->withToken($token)->postJson(route('api.app.forms.projects.store'), ['start_date' => '2026-12-10', 'end_date_expected' => '2026-12-01'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'customer_name', 'end_date_expected']);

        $response = $this->withToken($token)->postJson(route('api.app.forms.projects.store'), [
            'customer_id' => $customer->id, 'name' => 'Badkamer Jansen', 'value' => '12000', 'craftsmen' => [$craftsman->id],
        ])->assertCreated();
        $project = Project::latest('id')->first();
        $response->assertJsonPath('path', route('projects.show', $project, false));
        $this->assertTrue($project->craftsmen->contains($craftsman));

        $this->withToken($token)->getJson(route('api.app.forms.projects.edit', $project))->assertOk()
            ->assertJsonPath('values.name', 'Badkamer Jansen')
            ->assertJsonPath('values.craftsmen', [$craftsman->id])
            ->assertJsonPath('values.current_phase', 0)
            ->assertJsonPath('has_cover', false)
            ->assertJsonCount(9, 'phases');

        // Met een foto: FormData via POST met _method=PATCH, een lege lijst uitvoerders haalt iedereen eraf.
        $this->withToken($token)->post(route('api.app.forms.projects.update', $project), [
            '_method' => 'PATCH',
            'customer_id' => $customer->id,
            'name' => 'Badkamer en toilet Jansen',
            'current_phase' => '2',
            'deposit_received' => '0',
            'craftsmen' => '',
            'cover_photo' => UploadedFile::fake()->image('omslag.jpg', 800, 600),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('path', route('projects.show', $project, false));

        $project->refresh();
        $this->assertSame('Badkamer en toilet Jansen', $project->name);
        $this->assertNotNull($project->cover_photo_path);
        Storage::assertExists($project->cover_photo_path);
        $this->assertCount(0, $project->craftsmen);
        $this->assertSame(PhaseStatus::Bezig, $project->phases()->where('position', 2)->first()->status);

        // Zonder foto gewoon JSON.
        $this->withToken($token)->patchJson(route('api.app.forms.projects.update', $project), ['name' => 'Toilet Jansen', 'deposit_received' => true])->assertOk();
        $this->assertSame('Toilet Jansen', $project->fresh()->name);
        $this->assertNotNull($project->fresh()->deposit_received_at);
    }

    public function test_a_quote_is_created_for_a_lead_and_its_lines_are_edited_while_it_is_a_concept(): void
    {
        $lead = Lead::factory()->for(Customer::factory()->create(['name' => 'Dhr. Ahmed']))->create(['status' => LeadStatus::Intake]);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.forms.quotes.create', ['aanvraag' => $lead->id]))->assertOk()
            ->assertJsonPath('lead.customer_id', $lead->customer_id)
            ->assertJsonPath('lead.name', 'Dhr. Ahmed')
            ->assertJsonPath('valid_until', now()->addDays(30)->toDateString());

        $this->withToken($token)->postJson(route('api.app.forms.quotes.store'), [])->assertUnprocessable()->assertJsonValidationErrors('customer_id');

        $response = $this->withToken($token)->postJson(route('api.app.forms.quotes.store'), ['customer_id' => $lead->customer_id, 'lead_id' => $lead->id])->assertCreated();
        $quote = Quote::latest('id')->first();
        $response->assertJsonPath('path', route('quotes.show', $quote, false))->assertJsonPath('builder', route('quotes.builder', $quote, false));
        $this->assertSame(LeadStatus::Offerte, $lead->fresh()->status);

        $this->withToken($token)->getJson(route('api.app.forms.quotes.edit', $quote))->assertOk()
            ->assertJsonPath('values.lines', [])
            ->assertJsonPath('cancel', route('quotes.show', $quote, false));

        $this->withToken($token)->patchJson(route('api.app.forms.quotes.update', $quote), ['lines' => [['description' => '', 'quantity' => 0, 'unit' => 'm²', 'unit_price' => 10, 'vat_rate' => 7]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.description', 'lines.0.quantity', 'lines.0.vat_rate']);

        $this->withToken($token)->patchJson(route('api.app.forms.quotes.update', $quote), [
            'valid_until' => now()->addDays(14)->toDateString(),
            'notes' => 'Intern',
            'lines' => [
                ['description' => 'Tegelwerk wanden', 'quantity' => '12.5', 'unit' => 'm²', 'unit_price' => '80', 'vat_rate' => '21', 'is_estimate' => false],
                ['description' => 'Sanitair', 'quantity' => 1, 'unit' => 'post', 'unit_price' => 1500, 'vat_rate' => 9, 'is_estimate' => true],
            ],
        ])->assertOk()->assertJsonPath('path', route('quotes.show', $quote, false));

        $quote->refresh();
        $this->assertCount(2, $quote->lines);
        $this->assertEquals(2500, (float) $quote->subtotal);
        $this->assertTrue((bool) $quote->lines->firstWhere('description', 'Sanitair')->is_estimate);
        $this->withToken($token)->getJson(route('api.app.forms.quotes.edit', $quote))->assertOk()
            ->assertJsonPath('values.lines.0.quantity', 12.5)
            ->assertJsonPath('values.lines.1.vat_rate', 9);
    }

    public function test_a_sent_quote_cannot_be_edited_from_the_app(): void
    {
        $quote = Quote::factory()->sent()->create(['customer_id' => Customer::factory()->create()->id]);
        QuoteLine::factory()->create(['quote_id' => $quote->id]);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.forms.quotes.edit', $quote))
            ->assertUnprocessable()->assertJsonPath('message', 'Alleen conceptoffertes kunnen worden bewerkt.');
        $this->withToken($token)->patchJson(route('api.app.forms.quotes.update', $quote), ['lines' => [['description' => 'X', 'quantity' => 1, 'unit' => 'post', 'unit_price' => 1, 'vat_rate' => 21]]])
            ->assertUnprocessable()->assertJsonPath('message', 'Alleen conceptoffertes kunnen worden bewerkt.');
        $this->assertSame(QuoteStatus::Verstuurd, $quote->fresh()->status);
    }

    public function test_a_calculation_is_created_from_a_lead_with_checked_lines(): void
    {
        $lead = Lead::factory()->for(Customer::factory()->create(['name' => 'Fam. Visser']))->create(['service' => 'Badkamerrenovatie', 'description' => 'Badkamer 6 m2']);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.forms.calculations.create', ['aanvraag' => $lead->id]))->assertOk()
            ->assertJsonPath('values.title', 'Calculatie Badkamerrenovatie - Fam. Visser')
            ->assertJsonPath('values.customer_id', $lead->customer_id)
            ->assertJsonPath('values.description', 'Badkamer 6 m2')
            ->assertJsonPath('lead.name', 'Fam. Visser');

        $this->withToken($token)->postJson(route('api.app.forms.calculations.store'), ['title' => ''])->assertUnprocessable()->assertJsonValidationErrors('title');

        $response = $this->withToken($token)->postJson(route('api.app.forms.calculations.store'), [
            'title' => 'Calculatie badkamer',
            'lead_id' => $lead->id,
            'description' => 'Badkamer 6 m2',
            'lines' => [['type' => 'arbeid', 'description' => 'Tegelen', 'quantity' => 6, 'unit' => 'm2', 'unit_price' => 45]],
        ])->assertCreated();

        $calculation = Calculation::latest('id')->first();
        $response->assertJsonPath('path', route('calculations.show', $calculation, false));
        $this->assertSame($lead->customer_id, $calculation->customer_id);
        $this->assertCount(1, $calculation->lines);
    }

    public function test_a_budget_and_a_price_request_are_created_with_the_web_defaults(): void
    {
        $project = Project::factory()->create(['address' => 'Mercatorweg 28', 'city' => 'Arnhem']);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.forms.budgets.create', ['project' => $project->id]))->assertOk()
            ->assertJsonPath('values.project_id', $project->id)
            ->assertJsonPath('values.hourly_rate', 55)
            ->assertJsonFragment(['value' => 'renovatie', 'label' => 'Renovatie']);

        $response = $this->withToken($token)->postJson(route('api.app.forms.budgets.store'), ['name' => 'Badkamer', 'project_id' => $project->id])->assertCreated();
        $budget = Budget::latest('id')->first();
        $response->assertJsonPath('path', route('budgets.show', $budget, false));
        $this->assertSame('Mercatorweg 28, Arnhem', $budget->location);
        $this->assertGreaterThan(0, $budget->chapters()->count());

        $this->withToken($token)->getJson(route('api.app.forms.rfqs.create', ['project' => $project->id]))->assertOk()
            ->assertJsonPath('values.project_id', $project->id)
            ->assertJsonPath('values.deadline', today()->addWeek()->toDateString());

        $this->withToken($token)->postJson(route('api.app.forms.rfqs.store'), ['project_id' => $project->id])
            ->assertUnprocessable()->assertJsonValidationErrors('title');
        $response = $this->withToken($token)->postJson(route('api.app.forms.rfqs.store'), ['project_id' => $project->id, 'title' => 'Tegelwerk', 'budget' => 4000])->assertCreated();
        $rfq = Rfq::latest('id')->first();
        $response->assertJsonPath('path', route('rfqs.show', $rfq, false));
        $this->assertEquals(4000, (float) $rfq->budget);
    }

    public function test_team_members_are_managed_only_by_management_and_you_cannot_change_your_own_role(): void
    {
        $this->withToken($this->token(UserRole::Sales))->getJson(route('api.app.forms.team.create'))->assertForbidden();
        $this->withToken($this->token(UserRole::Sales))->postJson(route('api.app.forms.team.store'), [])->assertForbidden();

        // Sanctum onthoudt de gebruiker binnen een test: eerst vergeten, dan pas het andere token.
        $this->app['auth']->forgetGuards();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.forms.team.create'))->assertOk()
            ->assertJsonPath('values.role', 'uitvoerder')
            ->assertJsonMissing(['value' => 'klant']);

        $this->withToken($token)->postJson(route('api.app.forms.team.store'), ['name' => 'Piet', 'email' => 'piet@example.test', 'role' => 'klant', 'password' => 'kort'])
            ->assertUnprocessable()->assertJsonValidationErrors(['role', 'password']);

        $this->withToken($token)->postJson(route('api.app.forms.team.store'), ['name' => 'Piet', 'email' => 'piet@example.test', 'role' => 'uitvoerder', 'password' => 'geheim123'])
            ->assertCreated()->assertJsonPath('path', route('team.index', absolute: false));
        $piet = User::where('email', 'piet@example.test')->first();

        $this->withToken($token)->patchJson(route('api.app.forms.team.update', $piet), ['name' => 'Piet de Vries', 'email' => 'piet@example.test', 'role' => 'projectleider', 'password' => ''])->assertOk();
        $this->assertSame(UserRole::Projectleider, $piet->fresh()->role);

        $this->withToken($token)->getJson(route('api.app.forms.team.edit', $admin))->assertOk()->assertJsonPath('self', true);
        $this->withToken($token)->patchJson(route('api.app.forms.team.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'uitvoerder'])->assertOk();
        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_executors_cannot_open_the_office_forms(): void
    {
        $token = $this->token(UserRole::Uitvoerder);

        foreach (['leads.create', 'projects.create', 'quotes.create', 'calculations.create', 'budgets.create', 'rfqs.create', 'team.create'] as $route) {
            $this->withToken($token)->getJson(route('api.app.forms.'.$route))->assertForbidden();
        }
        $this->withToken($token)->postJson(route('api.app.forms.leads.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_the_web_forms_still_redirect_like_before(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->post(route('leads.store'), ['name' => 'Familie Web'])
            ->assertRedirect(route('leads.show', Lead::latest('id')->first()));
        $this->post(route('quotes.store'), ['customer_id' => Customer::first()->id])
            ->assertRedirect(route('quotes.builder', Quote::latest('id')->first()));
    }
}
