<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgentApproval;
use App\Models\AgentRun;
use App\Models\AgentSkillSetting;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Services\AgentOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Goedkeuringen, uitvoeringen, skills en de dagbriefing van Nova als eigen schermen in de app. */
class AppAgentsTest extends TestCase
{
    use RefreshDatabase;

    private function token(UserRole $role): string
    {
        return User::factory()->create(['role' => $role])->createToken('test')->plainTextToken;
    }

    private function approval(): AgentApproval
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'name' => 'Badkamer Jansen']);
        $orchestrator = new AgentOrchestrator;
        $run = $orchestrator->start('propose_schedule_change', $project, $leider);

        return $orchestrator->requestApproval($run, 'Planning verschuiven', 'Vijf dagen later starten.', ['start' => '2026-12-01'], ['nieuwe_start' => '1 december']);
    }

    public function test_open_approvals_are_listed_and_decided_from_the_app(): void
    {
        $approval = $this->approval();
        $token = $this->token(UserRole::Projectleider);

        $this->withToken($token)->getJson(route('api.app.approvals'))->assertOk()
            ->assertJsonPath('can_decide', true)
            ->assertJsonPath('open.0.id', $approval->id)
            ->assertJsonPath('open.0.action', 'Planning verschuiven')
            ->assertJsonPath('open.0.agent.name', 'Milo')
            ->assertJsonPath('open.0.payload.0.label', 'Nieuwe start')
            ->assertJsonPath('open.0.payload.0.value', '1 december')
            ->assertJsonPath('decided', []);

        $this->withToken($token)->postJson(route('api.app.approvals.decide', $approval), ['besluit' => 'afwijzen', 'reden' => 'Eerst de klant bellen'])
            ->assertOk()
            ->assertJsonPath('status', AgentApproval::STATUS_DECLINED)
            ->assertJsonPath('message', 'Afgewezen. De agent voert dit niet uit.');

        $this->assertSame(AgentApproval::STATUS_DECLINED, $approval->fresh()->status);
        $this->assertSame(AgentRun::STATUS_CANCELLED, $approval->run->fresh()->status);

        $this->withToken($token)->getJson(route('api.app.approvals'))->assertOk()
            ->assertJsonPath('open', [])
            ->assertJsonPath('decided.0.status.label', 'Afgewezen')
            ->assertJsonPath('decided.0.status.classes', 'bg-red-100 text-red-800');

        // Al besloten: de melding van de orchestrator, geen tweede besluit.
        $this->withToken($token)->postJson(route('api.app.approvals.decide', $approval), ['besluit' => 'goedkeuren'])
            ->assertOk()->assertJsonPath('message', 'Dit verzoek is al besloten.');
        $this->withToken($token)->postJson(route('api.app.approvals.decide', $approval), ['besluit' => 'misschien'])
            ->assertUnprocessable();
    }

    public function test_runs_are_filtered_by_status_and_agent(): void
    {
        $approval = $this->approval();
        $token = $this->token(UserRole::Admin);

        $this->withToken($token)->getJson(route('api.app.agents'))->assertOk()
            ->assertJsonPath('filter', 'actief')
            ->assertJsonPath('open_approvals', 1)
            ->assertJsonPath('runs.0.id', $approval->agent_run_id)
            ->assertJsonPath('runs.0.status.label', 'Wacht op goedkeuring')
            ->assertJsonPath('runs.0.approvals.0.summary', 'Vijf dagen later starten.')
            ->assertJsonPath('tabs.0.key', 'actief')
            ->assertJsonPath('tabs.0.count', 1)
            ->assertJsonCount(8, 'agents');

        $this->withToken($token)->getJson(route('api.app.agents', ['status' => 'afgerond']))->assertOk()
            ->assertJsonPath('runs', []);
        $this->withToken($token)->getJson(route('api.app.agents', ['status' => 'alles', 'agent' => 'lex']))->assertOk()
            ->assertJsonPath('agent', 'lex')
            ->assertJsonPath('runs', []);
    }

    public function test_office_staff_outside_management_cannot_see_or_change_skills(): void
    {
        $token = $this->token(UserRole::Projectleider);

        $this->withToken($token)->getJson(route('api.app.skills'))->assertForbidden();
        $this->withToken($token)->patchJson(route('api.app.skills.update', 'propose_variation'), ['level' => 'observeren'])->assertForbidden();
        $this->assertDatabaseCount('agent_skill_settings', 0);
    }

    public function test_management_sees_and_changes_skills(): void
    {
        $token = $this->token(UserRole::Directie);
        $this->withToken($token)->getJson(route('api.app.skills'))->assertOk()
            ->assertJsonPath('can_edit', true)
            ->assertJsonCount(4, 'levels')
            ->assertJsonPath('sops.0.event', 'lead.created');

        $this->withToken($token)->patchJson(route('api.app.skills.update', 'propose_variation'), ['level' => 'observeren', 'paused' => true, 'limit_amount' => 500])
            ->assertOk()->assertJsonPath('message', fn (string $message) => str_ends_with($message, 'bijgewerkt.'));

        $setting = AgentSkillSetting::firstWhere('skill', 'propose_variation');
        $this->assertTrue($setting->paused);
        $this->assertSame('observeren', $setting->level);

        $skills = collect($this->withToken($token)->getJson(route('api.app.skills'))->json('agents'))->flatMap(fn (array $agent) => $agent['skills']);
        $skill = $skills->firstWhere('id', 'propose_variation');
        $this->assertTrue($skill['paused']);
        $this->assertSame(500.0, (float) $skill['limit']);
        $this->assertStringContainsString('boven € 500 altijd een mens', $skill['decides']);

        $this->withToken($token)->patchJson(route('api.app.skills.update', 'bestaat_niet'), ['level' => 'observeren'])->assertNotFound();
    }

    public function test_executors_cannot_open_the_agent_screens(): void
    {
        $token = $this->token(UserRole::Uitvoerder);

        $this->withToken($token)->getJson(route('api.app.approvals'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.agents'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.nova.briefing'))->assertForbidden();
    }

    public function test_the_briefing_screen_reads_the_nova_briefing(): void
    {
        config(['renovion.ai.api_key' => null]);

        $this->withToken($this->token(UserRole::Sales))->getJson(route('api.app.nova.briefing', ['refresh' => 1]))
            ->assertOk()->assertJsonPath('text', null);
    }

    public function test_the_website_still_redirects_after_a_decision(): void
    {
        $approval = $this->approval();

        $this->actingAs(User::factory()->create(['role' => UserRole::Projectleider]))
            ->from(route('approvals.index'))
            ->post(route('approvals.decide', $approval), ['besluit' => 'goedkeuren'])
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHas('success');
    }
}
