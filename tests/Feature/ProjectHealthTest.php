<?php

namespace Tests\Feature;

use App\Enums\DocumentCategory;
use App\Enums\HealthLight;
use App\Enums\PhaseStatus;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use App\Services\ProjectHealthService;
use App\Support\ProjectHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De stoplichtregels uit hoofdstuk 7 van de briefing.
 */
class ProjectHealthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Project in uitvoering (fase 4) met een compleet dossier, zodat elke test
     * alleen het signaal aanzet dat hij wil toetsen.
     */
    private function projectInUitvoering(array $attributes = []): Project
    {
        $project = Project::factory()->create([
            'start_date' => today()->subDays(10),
            'end_date_expected' => today()->addDays(10),
            'progress' => 50,
            'value' => 20000,
            'paid_amount' => 20000,
            'deposit_amount' => null,
            ...$attributes,
        ]);

        // Fasen 0 t/m 3 gereed: het project staat in fase 4 (Uitvoering).
        $project->phases()->where('position', '<', 4)->update([
            'status' => PhaseStatus::Gereed,
            'completed_at' => now(),
        ]);

        $project->phases()->where('position', 4)->update(['status' => PhaseStatus::Bezig]);

        Document::factory()->create([
            'documentable_type' => 'project',
            'documentable_id' => $project->id,
            'customer_id' => $project->customer_id,
            'category' => DocumentCategory::Contract,
        ]);

        return $project->fresh();
    }

    private function health(Project $project): ProjectHealth
    {
        return app(ProjectHealthService::class)->for($project->fresh());
    }

    public function test_a_project_before_phase_four_has_no_lights_yet(): void
    {
        $project = Project::factory()->create();

        $health = $this->health($project);

        $this->assertSame(HealthLight::Grijs, $health->overall);
        $this->assertSame(HealthLight::Grijs, $health->signal('planning')->light);
    }

    public function test_a_project_on_schedule_is_green_on_every_light(): void
    {
        $health = $this->health($this->projectInUitvoering());

        $this->assertSame(HealthLight::Groen, $health->overall);
        $this->assertSame(HealthLight::Groen, $health->signal('planning')->light);
        $this->assertSame(HealthLight::Groen, $health->signal('budget')->light);
        $this->assertSame(HealthLight::Groen, $health->signal('kwaliteit')->light);
        $this->assertSame(HealthLight::Groen, $health->signal('klant')->light);
        $this->assertFalse($health->needsAttention());
    }

    public function test_planning_is_red_when_the_delivery_date_has_passed(): void
    {
        $project = $this->projectInUitvoering([
            'start_date' => today()->subDays(30),
            'end_date_expected' => today()->subDay(),
            'progress' => 90,
        ]);

        $health = $this->health($project);

        $this->assertSame(HealthLight::Rood, $health->signal('planning')->light);
        $this->assertSame(HealthLight::Rood, $health->overall);
    }

    public function test_planning_is_red_at_ten_percent_behind_and_amber_at_five(): void
    {
        // Halverwege de looptijd: verwacht 50%.
        $rood = $this->projectInUitvoering(['progress' => 40]);
        $this->assertSame(HealthLight::Rood, $this->health($rood)->signal('planning')->light);

        $oranje = $this->projectInUitvoering(['progress' => 45]);
        $this->assertSame(HealthLight::Oranje, $this->health($oranje)->signal('planning')->light);

        $groen = $this->projectInUitvoering(['progress' => 46]);
        $this->assertSame(HealthLight::Groen, $this->health($groen)->signal('planning')->light);
    }

    public function test_planning_is_amber_when_the_start_is_near_without_a_team(): void
    {
        $project = $this->projectInUitvoering([
            'start_date' => today()->addDays(5),
            'end_date_expected' => today()->addDays(30),
            'progress' => 0,
        ]);

        $health = $this->health($project);

        $this->assertSame(HealthLight::Oranje, $health->signal('planning')->light);
        $this->assertStringContainsString('niemand ingepland', $health->signal('planning')->reason);
    }

    public function test_an_assigned_craftsman_clears_the_start_warning(): void
    {
        $project = $this->projectInUitvoering([
            'start_date' => today()->addDays(5),
            'end_date_expected' => today()->addDays(30),
            'progress' => 0,
        ]);

        $project->craftsmen()->attach(User::factory()->create());

        $this->assertSame(HealthLight::Groen, $this->health($project)->signal('planning')->light);
    }

    public function test_budget_is_amber_on_an_overdue_term_and_red_after_two_weeks(): void
    {
        $oranje = $this->projectInUitvoering([
            'paid_amount' => 5000,
            'next_payment_due_at' => today()->subDays(3),
        ]);
        $this->assertSame(HealthLight::Oranje, $this->health($oranje)->signal('budget')->light);

        $rood = $this->projectInUitvoering([
            'paid_amount' => 5000,
            'next_payment_due_at' => today()->subDays(20),
        ]);
        $this->assertSame(HealthLight::Rood, $this->health($rood)->signal('budget')->light);
    }

    public function test_budget_is_red_when_work_started_without_the_deposit(): void
    {
        $project = $this->projectInUitvoering([
            'deposit_amount' => 5000,
            'deposit_received_at' => null,
            'start_date' => today()->subDays(2),
        ]);

        $health = $this->health($project);

        $this->assertSame(HealthLight::Rood, $health->signal('budget')->light);
        $this->assertStringContainsString('aanbetaling', $health->signal('budget')->reason);
    }

    public function test_quality_is_red_on_a_blocked_work_package(): void
    {
        $project = $this->projectInUitvoering();

        WorkPackage::factory()->create([
            'project_id' => $project->id,
            'project_phase_id' => $project->phases()->where('position', 4)->value('id'),
            'name' => 'Elektra begane grond',
            'status' => PhaseStatus::Geblokkeerd,
        ]);

        $health = $this->health($project);

        $this->assertSame(HealthLight::Rood, $health->signal('kwaliteit')->light);
        $this->assertStringContainsString('Elektra begane grond', $health->signal('kwaliteit')->reason);
    }

    public function test_leftovers_make_quality_amber_and_more_than_three_make_it_red(): void
    {
        $project = $this->projectInUitvoering();
        $voorOplevering = $project->phases()->where('position', 6)->value('id');

        WorkPackage::factory()->count(2)->create([
            'project_id' => $project->id,
            'project_phase_id' => $voorOplevering,
            'status' => PhaseStatus::Bezig,
        ]);

        $this->assertSame(HealthLight::Oranje, $this->health($project)->signal('kwaliteit')->light);

        WorkPackage::factory()->count(2)->create([
            'project_id' => $project->id,
            'project_phase_id' => $voorOplevering,
            'status' => PhaseStatus::Bezig,
        ]);

        $this->assertSame(HealthLight::Rood, $this->health($project)->signal('kwaliteit')->light);
    }

    public function test_a_missing_contract_makes_quality_amber(): void
    {
        $project = $this->projectInUitvoering();
        $project->documents()->delete();

        $health = $this->health($project);

        $this->assertSame(HealthLight::Oranje, $health->signal('kwaliteit')->light);
        $this->assertStringContainsString('aannemingsovereenkomst', $health->signal('kwaliteit')->reason);
    }

    public function test_client_is_amber_while_a_phase_waits_on_the_customer(): void
    {
        $project = $this->projectInUitvoering();
        $project->phases()->where('position', 4)->update(['status' => PhaseStatus::WachtOpKlant]);

        $health = $this->health($project);

        $this->assertSame(HealthLight::Oranje, $health->signal('klant')->light);
        $this->assertStringContainsString('Wacht op de klant', $health->signal('klant')->reason);
    }

    public function test_the_overall_light_is_the_worst_of_the_four(): void
    {
        $project = $this->projectInUitvoering(['progress' => 40]);
        $project->phases()->where('position', 4)->update(['status' => PhaseStatus::WachtOpKlant]);

        $health = $this->health($project);

        $this->assertSame(HealthLight::Rood, $health->overall);
        $this->assertSame(HealthLight::Oranje, $health->signal('klant')->light);
        $this->assertNotNull($health->biggestBlocker);
    }

    public function test_schedule_variance_reports_how_far_behind_the_project_runs(): void
    {
        $health = $this->health($this->projectInUitvoering(['progress' => 30]));

        $this->assertSame(50, $health->expectedProgress);
        $this->assertSame(20, $health->scheduleVariance());
    }

    public function test_many_projects_are_scored_without_a_query_per_row(): void
    {
        Project::factory()->count(5)->create();
        $projects = Project::with(ProjectHealthService::RELATIONS)->get();

        \DB::enableQueryLog();
        $health = app(ProjectHealthService::class)->forMany($projects);
        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        $this->assertCount(5, $health);
        $this->assertCount(0, $queries, 'Het portfolio mag geen extra queries per project doen.');
    }
}
