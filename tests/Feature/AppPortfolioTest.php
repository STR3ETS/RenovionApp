<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Het portfolio met stoplichten als eigen scherm in de app. */
class AppPortfolioTest extends TestCase
{
    use RefreshDatabase;

    private function lateProject(): Project
    {
        $project = Project::factory()->create([
            'name' => 'Badkamer Jansen',
            'start_date' => today()->subDays(30),
            'end_date_expected' => today()->subDay(),
            'progress' => 60,
        ]);
        $project->phases()->where('position', '<', 4)->update(['status' => PhaseStatus::Gereed]);
        $project->phases()->where('position', 4)->update(['status' => PhaseStatus::Bezig]);

        return $project;
    }

    public function test_the_portfolio_shows_lights_progress_milestone_and_blocker(): void
    {
        $project = $this->lateProject();
        Project::factory()->create(['name' => 'Nog niet gestart']);
        $token = User::factory()->create(['role' => UserRole::Projectleider])->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(route('api.app.portfolio'))->assertOk()
            ->assertJsonPath('sort', 'gezondheid')
            ->assertJsonPath('filter', null)
            ->assertJsonPath('counts.0.value', 'rood')
            ->assertJsonPath('counts.0.count', 1)
            ->assertJsonPath('projects.0.id', $project->id)
            ->assertJsonPath('projects.0.overall.label', 'Actie nodig')
            ->assertJsonPath('projects.0.overall.classes', 'bg-red-100 text-red-800')
            ->assertJsonPath('projects.0.path', route('projects.show', $project, false))
            ->assertJsonCount(4, 'projects.0.signals')
            ->assertJsonCount(2, 'projects');

        $this->assertNotNull($response->json('projects.0.milestone'));
        $this->assertNotNull($response->json('projects.0.plan'));
    }

    public function test_the_portfolio_filters_on_a_light_and_validates_the_query(): void
    {
        $this->lateProject();
        Project::factory()->create(['name' => 'Nog niet gestart']);
        $token = User::factory()->create(['role' => UserRole::Admin])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.portfolio', ['licht' => 'rood', 'sorteer' => 'waarde']))->assertOk()
            ->assertJsonPath('filter', 'rood')
            ->assertJsonPath('sort', 'waarde')
            ->assertJsonCount(1, 'projects')
            ->assertJsonPath('projects.0.name', 'Badkamer Jansen');

        $this->withToken($token)->getJson(route('api.app.portfolio', ['licht' => 'paars']))->assertUnprocessable();
    }

    public function test_executors_cannot_open_the_portfolio(): void
    {
        $token = User::factory()->create(['role' => UserRole::Uitvoerder])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.portfolio'))->assertForbidden();
    }
}
