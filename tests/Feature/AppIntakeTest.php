<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Intake;
use App\Models\Lead;
use App\Models\ScheduleEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Intakes als eigen schermen in de app: starten, stappen opslaan, opname plannen en afronden. */
class AppIntakeTest extends TestCase
{
    use RefreshDatabase;

    private function token(): string
    {
        return User::factory()->create()->createToken('test')->plainTextToken;
    }

    public function test_a_lead_without_intake_is_started_from_the_list(): void
    {
        $lead = Lead::factory()->for(Customer::factory()->create(['name' => 'Fam. Bakker', 'phone' => '0612345678']))->create(['service' => 'Badkamerrenovatie']);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.intakes.index'))->assertOk()
            ->assertJsonPath('without.count', 1)
            ->assertJsonPath('without.leads.0.id', $lead->id)
            ->assertJsonPath('open', [])
            ->assertJsonCount(5, 'steps');

        $response = $this->withToken($token)->postJson(route('api.app.intakes.store'), ['lead_id' => $lead->id])->assertOk();
        $intake = Intake::firstWhere('lead_id', $lead->id);
        $response->assertJsonPath('path', route('intakes.show', $intake, false));
        $this->assertSame(LeadStatus::Contact, $lead->fresh()->status);

        $this->withToken($token)->getJson(route('api.app.intakes.index'))->assertOk()
            ->assertJsonPath('without.count', 0)
            ->assertJsonPath('open.0.id', $intake->id)
            ->assertJsonPath('open.0.name', 'Fam. Bakker');
    }

    public function test_steps_are_saved_a_survey_is_planned_and_the_intake_is_completed(): void
    {
        $lead = Lead::factory()->for(Customer::factory()->create(['name' => 'Fam. Bakker', 'phone' => '0612345678', 'city' => 'Arnhem']))
            ->status(LeadStatus::Contact)->create(['service' => 'Badkamerrenovatie']);
        $intake = Intake::create(['lead_id' => $lead->id, 'customer_id' => $lead->customer_id, 'answers' => [], 'step' => 1]);
        $surveyor = User::factory()->create(['role' => UserRole::Projectleider, 'name' => 'Raphael']);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.intakes.show', $intake))->assertOk()
            ->assertJsonPath('name', 'Fam. Bakker')
            ->assertJsonPath('step', 1)
            ->assertJsonPath('complete', false)
            ->assertJsonPath('answers.wensen', '')
            ->assertJsonPath('customer.phone', '0612345678')
            ->assertJsonPath('surveyors.0.id', $surveyor->id);

        $this->withToken($token)->patchJson(route('api.app.intakes.update', $intake), [
            'step' => 1,
            'answers' => ['wensen' => 'Inloopdouche', 'budget' => '15.000', 'startWens' => 'Voorjaar'],
        ])->assertOk()->assertJsonPath('step', 2);

        $this->withToken($token)->postJson(route('api.app.intakes.complete', $intake))->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Nog niet compleet. Dit mist: '));

        $this->withToken($token)->postJson(route('api.app.intakes.survey', $intake), [
            'surveyor_id' => $surveyor->id, 'survey_on' => now()->addDays(2)->toDateString(), 'survey_at' => '10:30',
        ])->assertOk()->assertJsonPath('message', 'Opname gepland en in de planning gezet.');
        $this->assertSame(LeadStatus::Intake, $lead->fresh()->status);
        $this->assertTrue(ScheduleEntry::where('user_id', $surveyor->id)->where('lead_id', $lead->id)->exists());

        $this->withToken($token)->patchJson(route('api.app.intakes.update', $intake), [
            'step' => 3,
            'answers' => ['situatie' => 'Oud bad', 'afmetingen' => '2 x 3 m', 'aandacht' => 'Geen', 'bereikbaarheid' => 'Begane grond'],
        ])->assertOk()->assertJsonPath('percentage', 100);

        $this->withToken($token)->getJson(route('api.app.intakes.show', $intake))->assertOk()
            ->assertJsonPath('survey.at', '10:30')
            ->assertJsonPath('survey.who', 'met Raphael')
            ->assertJsonPath('missing', []);

        $this->withToken($token)->postJson(route('api.app.intakes.complete', $intake))->assertOk();
        $this->assertNotNull($intake->fresh()->completed_at);
        $this->withToken($token)->getJson(route('api.app.intakes.index'))->assertOk()->assertJsonPath('done.0.id', $intake->id);
    }

    public function test_the_web_flow_still_redirects(): void
    {
        $lead = Lead::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('intakes.store'), ['lead_id' => $lead->id])
            ->assertRedirect(route('intakes.show', Intake::firstWhere('lead_id', $lead->id)));
    }

    public function test_executors_cannot_open_intakes(): void
    {
        $intake = Intake::create(['customer_id' => Customer::factory()->create()->id, 'answers' => []]);
        $token = User::factory()->create(['role' => UserRole::Uitvoerder])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.intakes.index'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.intakes.show', $intake))->assertForbidden();
    }
}
