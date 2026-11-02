<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgentApproval;
use App\Models\AgentEvent;
use App\Models\AgentRun;
use App\Models\AgentSkillSetting;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Services\AgentOrchestrator;
use App\Services\AttentionService;
use App\Support\AgentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_skill_belongs_to_a_known_agent_and_level(): void
    {
        foreach (AgentRegistry::SKILLS as $id => $skill) {
            $this->assertArrayHasKey($skill['agent'], AgentRegistry::AGENTS, "Skill {$id} verwijst naar een onbekende agent.");
            $this->assertArrayHasKey($skill['level'], AgentRegistry::LEVELS, "Skill {$id} heeft een onbekend niveau.");
        }

        // Elke standaardprocedure verwijst naar bestaande skills.
        foreach (AgentRegistry::SOPS as $event => $sop) {
            foreach ($sop['skills'] as $skill) {
                $this->assertArrayHasKey($skill, AgentRegistry::SKILLS, "SOP {$event} verwijst naar onbekende skill {$skill}.");
            }
        }
    }

    public function test_preparing_work_always_needs_a_human_decision(): void
    {
        $this->assertTrue(AgentRegistry::needsApproval('voorbereiden'));
        $this->assertTrue(AgentRegistry::needsApproval('begrensd'));
        // Observeren en protocol vragen niets: die signaleren of voeren uit.
        $this->assertFalse(AgentRegistry::needsApproval('observeren'));
        $this->assertFalse(AgentRegistry::needsApproval('protocol'));
    }

    public function test_a_paused_skill_does_not_run(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $orchestrator = new AgentOrchestrator;

        AgentSkillSetting::create(['skill' => 'propose_calculation_lines', 'paused' => true]);

        $refusal = null;
        $run = $orchestrator->start('propose_calculation_lines', null, $leider, refusal: $refusal);

        $this->assertNull($run);
        $this->assertStringContainsString('op pauze', $refusal);
        $this->assertDatabaseCount('agent_runs', 0);
    }

    public function test_starting_a_skill_records_the_agent_and_the_event(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);

        $run = (new AgentOrchestrator)->start('propose_schedule_change', $project, $leider, 'incident');

        $this->assertNotNull($run);
        $this->assertSame('milo', $run->agent);
        $this->assertSame(AgentRun::STATUS_RUNNING, $run->status);
        $this->assertSame('incident', $run->trigger);

        $event = AgentEvent::firstWhere('type', 'run.started');

        $this->assertNotNull($event);
        $this->assertSame('agent:milo', $event->actor);
        $this->assertTrue($event->isAgent());
        $this->assertSame('Milo', $event->actorName());
    }

    public function test_an_approval_records_what_was_proposed(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $orchestrator = new AgentOrchestrator;

        $run = $orchestrator->start('propose_schedule_change', $project, $leider);
        $voorstel = ['start' => '2026-12-01', 'dagen' => 5];

        $approval = $orchestrator->requestApproval(
            $run,
            'Planning verschuiven naar 1 december',
            'Vijf dagen later starten omdat de tegels later komen.',
            $voorstel,
            ['nieuwe start' => '1 december', 'gevolg' => 'oplevering schuift een week'],
        );

        $this->assertTrue($approval->isOpen());
        $this->assertSame('Projectleider', $approval->approver_role);
        $this->assertSame(AgentRun::STATUS_WAITING_APPROVAL, $run->fresh()->status);
        $this->assertTrue($run->fresh()->isWaiting());
        $this->assertTrue($approval->matches($voorstel));
        $this->assertFalse($approval->matches(['start' => '2026-12-08', 'dagen' => 5]));
    }

    public function test_approving_closes_the_run_and_names_who_decided(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $orchestrator = new AgentOrchestrator;

        $run = $orchestrator->start('propose_schedule_change', $project, $leider);
        $approval = $orchestrator->requestApproval($run, 'Planning verschuiven', 'Vijf dagen later.', ['start' => '2026-12-01']);

        $this->actingAs($leider)->post(route('approvals.decide', $approval), [
            'besluit' => 'goedkeuren',
        ])->assertSessionHas('success');

        $approval->refresh();

        $this->assertSame(AgentApproval::STATUS_APPROVED, $approval->status);
        $this->assertSame($leider->id, $approval->decided_by);
        $this->assertSame(AgentRun::STATUS_DONE, $run->fresh()->status);

        $event = AgentEvent::firstWhere('type', 'approval.granted');

        $this->assertNotNull($event);
        // Een mens besloot dit, en dat staat als zodanig in de audit.
        $this->assertFalse($event->isAgent());
        $this->assertSame($leider->name, $event->actorName());
    }

    public function test_an_approval_lapses_when_the_proposal_changed(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $orchestrator = new AgentOrchestrator;

        $run = $orchestrator->start('propose_variation', $project, $leider);
        $approval = $orchestrator->requestApproval($run, 'Meerwerk twee stopcontacten', 'EUR 180.', ['prijs' => 180]);

        // Het voorstel is inmiddels anders: dan is de goedkeuring niet geldig.
        $result = $orchestrator->decide($approval, true, $leider, currentSubject: ['prijs' => 260]);

        $this->assertSame(AgentApproval::STATUS_STALE, $result['status']);
        $this->assertStringContainsString('gewijzigd', $result['message']);
        $this->assertSame(AgentApproval::STATUS_STALE, $approval->fresh()->status);
        $this->assertNotNull(AgentEvent::firstWhere('type', 'approval.stale'));
    }

    public function test_changing_the_subject_invalidates_open_approvals_for_that_action(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $orchestrator = new AgentOrchestrator;

        $run = $orchestrator->start('create_quote_draft', $project, $leider);
        $orchestrator->requestApproval($run, 'Conceptofferte versturen', 'Versie 1.', ['versie' => 1]);

        $count = $orchestrator->invalidate($project->id, 'Conceptofferte versturen', 'De calculatie is aangepast.');

        $this->assertSame(1, $count);
        $this->assertSame(0, AgentApproval::open()->count());
    }

    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $orchestrator = new AgentOrchestrator;

        $run = $orchestrator->start('propose_variation', null, $leider);
        $approval = $orchestrator->requestApproval($run, 'Meerwerk', 'EUR 100.', ['prijs' => 100]);

        $orchestrator->decide($approval, true, $leider);
        $result = $orchestrator->decide($approval->fresh(), false, $leider);

        $this->assertStringContainsString('al besloten', $result['message']);
        $this->assertSame(AgentApproval::STATUS_APPROVED, $approval->fresh()->status);
    }

    public function test_a_skill_setting_overrides_the_default_level(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertSame('observeren', AgentSkillSetting::effective('budget_forecast')['level']);

        $this->actingAs($admin)->patch(route('skills.update', 'budget_forecast'), [
            'level' => 'voorbereiden',
            'limit_amount' => 5000,
        ])->assertSessionHas('success');

        $effective = AgentSkillSetting::effective('budget_forecast');

        $this->assertSame('voorbereiden', $effective['level']);
        $this->assertSame(5000.0, $effective['limit']);
        $this->assertTrue($effective['overridden']);
    }

    public function test_only_management_configures_skills(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);

        // Een projectleider mag de skills bekijken maar niet instellen.
        $this->actingAs($leider)->get(route('agents.index'))->assertOk();
        $this->actingAs($leider)->patch(route('skills.update', 'budget_forecast'), ['level' => 'begrensd'])->assertForbidden();

        $this->actingAs($leider)->get(route('skills.index'))->assertForbidden();
    }

    public function test_open_approvals_show_up_in_the_inbox_as_something_to_confirm(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'name' => 'Badkamer Peters']);
        $orchestrator = new AgentOrchestrator;

        $run = $orchestrator->start('propose_variation', $project, $leider);
        $orchestrator->requestApproval($run, 'Meerwerk twee stopcontacten', 'EUR 180 en een halve dag extra.', ['prijs' => 180]);

        $items = (new AttentionService)->items();
        $item = $items->firstWhere('title', 'Meerwerk twee stopcontacten');

        $this->assertNotNull($item);
        $this->assertSame('bevestigen', $item['kind']);
        $this->assertStringContainsString('Finn', $item['label']);
        $this->assertStringContainsString('Badkamer Peters', $item['subtitle']);
    }

    public function test_the_agent_screens_open(): void
    {
        $directie = User::factory()->create(['role' => UserRole::Directie]);
        $orchestrator = new AgentOrchestrator;
        $run = $orchestrator->start('budget_forecast', null, $directie);
        $orchestrator->finish($run, 'Prognose bijgewerkt.', 0.02);

        $this->actingAs($directie)->get(route('agents.index'))->assertOk()->assertSee('Modelkosten');
        $this->actingAs($directie)->get(route('approvals.index'))->assertOk();
        $this->actingAs($directie)->get(route('audit.index'))->assertOk()->assertSee('run.finished');
        $this->actingAs($directie)->get(route('skills.index'))->assertOk()->assertSee('Standaard');
    }
}
