<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_portfolio_shows_running_projects_with_their_lights(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()->create([
            'name' => 'Badkamer Jansen',
            'start_date' => today()->subDays(30),
            'end_date_expected' => today()->subDay(),
            'progress' => 60,
        ]);

        $project->phases()->where('position', '<', 4)->update(['status' => PhaseStatus::Gereed]);
        $project->phases()->where('position', 4)->update(['status' => PhaseStatus::Bezig]);

        $this->actingAs($user)->get('/portfolio')
            ->assertOk()
            ->assertSee('Badkamer Jansen')
            ->assertSee('Actie nodig')
            ->assertSee('Volgende mijlpaal')
            ->assertSee('Grootste blokkade');
    }

    public function test_the_portfolio_can_be_filtered_on_a_light(): void
    {
        $user = User::factory()->create();

        $rood = Project::factory()->create([
            'name' => 'Loopt uit',
            'start_date' => today()->subDays(30),
            'end_date_expected' => today()->subDay(),
        ]);
        $rood->phases()->where('position', '<', 4)->update(['status' => PhaseStatus::Gereed]);
        $rood->phases()->where('position', 4)->update(['status' => PhaseStatus::Bezig]);

        Project::factory()->create(['name' => 'Nog niet gestart']);

        $this->actingAs($user)->get('/portfolio?licht=rood')
            ->assertOk()
            ->assertSee('Loopt uit')
            ->assertDontSee('Nog niet gestart');
    }

    public function test_uitvoerders_cannot_open_the_portfolio(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get('/portfolio')->assertForbidden();
    }
}
