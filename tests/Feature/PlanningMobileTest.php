<?php

namespace Tests\Feature;

use App\Enums\ScheduleEntryType;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningMobileTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_sees_who_is_free_per_day(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(9, 0));
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $peter = User::factory()->create(['name' => 'Peter de Vries', 'role' => UserRole::Uitvoerder]);
        User::factory()->create(['name' => 'Mehmet', 'role' => UserRole::Uitvoerder]);
        $project = Project::factory()->create(['name' => 'Badkamer Jansen', 'address' => 'Dorpsstraat 1', 'city' => 'Arnhem']);
        ScheduleEntry::factory()->create([
            'user_id' => $peter->id, 'project_id' => $project->id, 'type' => ScheduleEntryType::Project,
            'date' => today(), 'start_time' => '08:00', 'end_time' => '16:00', 'title' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('planning.index'));

        $response->assertOk()
            ->assertSee('Vandaag, ')
            ->assertSee('Vrij')
            ->assertSee('08:00-16:00, Project')
            ->assertSee('day: 2', false);

        // Detailpaneel: adres en routelink uit het project, verwijderen alleen voor de planner.
        $details = $response->viewData('details');
        $detail = $details->first();
        $this->assertSame('Dorpsstraat 1, Arnhem', $detail['address']);
        $this->assertStringContainsString('google.com/maps', $detail['maps']);
        $this->assertNotNull($detail['delete']);
    }

    public function test_uitvoerder_sees_an_agenda_from_today_without_other_peoples_planning(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(1)->setTime(9, 0));
        $peter = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $mehmet = User::factory()->create(['role' => UserRole::Uitvoerder]);
        ScheduleEntry::factory()->create(['user_id' => $peter->id, 'date' => today(), 'title' => 'Tegelwerk De Vries', 'type' => ScheduleEntryType::Project]);
        ScheduleEntry::factory()->create(['user_id' => $mehmet->id, 'date' => today(), 'title' => 'Stucwerk Smit', 'type' => ScheduleEntryType::Project]);
        ScheduleEntry::factory()->create(['user_id' => $peter->id, 'date' => today()->subDay(), 'title' => 'Gisteren klus', 'type' => ScheduleEntryType::Project]);

        $response = $this->actingAs($peter)->get(route('planning.index'));

        $response->assertOk()
            ->assertSee('Vandaag')
            ->assertSee('Morgen')
            ->assertSee('Tegelwerk De Vries')
            ->assertDontSee('Stucwerk Smit')
            ->assertDontSee('+ Inplannen');
        $this->assertNull($response->viewData('details')->first()['delete']);
    }
}
