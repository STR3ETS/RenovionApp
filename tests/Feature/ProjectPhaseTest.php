<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_project_gets_all_default_phases(): void
    {
        $project = Project::factory()->create();

        $this->assertCount(count(ProjectPhase::NAMES), $project->phases);
        $this->assertSame('Opdracht & overdracht', $project->phases->first()->name);
        $this->assertSame(PhaseStatus::Bezig, $project->phases->first()->status);
        $this->assertSame(PhaseStatus::NietGestart, $project->phases->last()->status);
        $this->assertSame('Opdracht & overdracht', $project->currentPhase()->name);
    }

    public function test_phases_are_shown_on_the_project_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($user)->get('/projecten/'.$project->id)
            ->assertOk()
            ->assertSee('Projectvoortgang')
            ->assertSee('Werkvoorbereiding')
            ->assertSee('Oplevering');
    }

    public function test_moving_a_project_to_a_phase_completes_earlier_phases(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($user)->patch('/projecten/'.$project->id, [
            'current_phase' => 4, // Uitvoering
        ])->assertRedirect(route('projects.show', $project));

        $project->refresh()->load('phases');

        $this->assertSame(PhaseStatus::Gereed, $project->phases->firstWhere('position', 0)->status);
        $this->assertNotNull($project->phases->firstWhere('position', 3)->completed_at);
        $this->assertSame(PhaseStatus::Bezig, $project->phases->firstWhere('position', 4)->status);
        $this->assertSame(PhaseStatus::NietGestart, $project->phases->firstWhere('position', 5)->status);
        $this->assertSame('Uitvoering', $project->currentPhase()->name);
    }

    public function test_moving_back_to_an_earlier_phase_resets_later_phases(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($user)->patch('/projecten/'.$project->id, ['current_phase' => 6]);
        $this->actingAs($user)->patch('/projecten/'.$project->id, ['current_phase' => 2]);

        $project->refresh()->load('phases');

        $this->assertSame(PhaseStatus::Bezig, $project->phases->firstWhere('position', 2)->status);
        $this->assertSame(PhaseStatus::NietGestart, $project->phases->firstWhere('position', 4)->status);
        $this->assertNull($project->phases->firstWhere('position', 4)->completed_at);
    }
}
