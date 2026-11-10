<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Project;
use App\Models\SnagItem;
use App\Models\User;
use App\Support\ProjectTabs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDossierTest extends TestCase
{
    use RefreshDatabase;

    private function project(): Project
    {
        return Project::factory()->create([
            'customer_id' => Customer::factory(),
            'name' => 'Badkamer Jansen',
            'type' => 'Badkamerrenovatie',
            'address' => 'Lindenlaan 12',
            'city' => 'Utrecht',
            'value' => 18500,
        ]);
    }

    public function test_the_header_shows_the_six_facts_that_belong_on_a_dossier(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $project = $this->project();

        $this->actingAs($admin)->get(route('projects.show', $project))
            ->assertOk()
            // Kruimelpad, kop en adres.
            ->assertSee('Projectdossiers')
            ->assertSee('Badkamer Jansen')
            ->assertSee('Lindenlaan 12, Utrecht')
            // De metadatastrip.
            ->assertSee('Projectnummer')
            ->assertSee('Opdrachtgever')
            ->assertSee('Projectleider')
            ->assertSee('Projectwaarde')
            ->assertSee('EUR 18.500,00');
    }

    public function test_every_tab_opens_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $project = $this->project();

        foreach (array_keys(ProjectTabs::TABS) as $tab) {
            $this->actingAs($admin)->get(route('projects.show', [$project, 'tab' => $tab]))
                ->assertOk("Tabblad {$tab} geeft geen 200.");
        }
    }

    public function test_a_craftsman_only_gets_the_tabs_about_their_own_work(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $project = $this->project();
        $project->craftsmen()->attach($uitvoerder);

        $tabs = collect(ProjectTabs::forUser($uitvoerder, $project, 'overzicht'))->pluck('key');

        // Wat hij wel nodig heeft op de bouwplaats.
        $this->assertTrue($tabs->contains('uitvoering'));
        $this->assertTrue($tabs->contains('werkbonnen'));
        $this->assertTrue($tabs->contains('fotos'));

        // En wat hem niet aangaat.
        foreach (['financien', 'nacalculatie', 'begroting', 'offerte', 'inkoop', 'agents'] as $forbidden) {
            $this->assertFalse($tabs->contains($forbidden), "Een uitvoerder ziet het tabblad {$forbidden} en dat hoort niet.");
        }
    }

    public function test_an_unknown_tab_falls_back_to_the_overview(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $project = $this->project();

        // De kop staat opgesplitst in de opmaak: "Project<span>voortgang</span>".
        $this->actingAs($admin)->get(route('projects.show', [$project, 'tab' => 'verzonnen']))
            ->assertOk()
            ->assertSeeInOrder(['Project', 'voortgang'], escape: false);

        // En een tabblad dat deze rol niet mag, ook.
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $project->craftsmen()->attach($uitvoerder);

        $this->assertSame('overzicht', ProjectTabs::resolve($uitvoerder, $project, 'financien'));
    }

    public function test_the_orange_button_points_at_the_next_step_in_the_process(): void
    {
        $project = $this->project();

        // Helemaal aan het begin wijst hij naar de begroting.
        $this->assertSame('begroting', ProjectTabs::nextStep($project)['tab']);

        // Zijn de eerste fasen gereed, dan verder in het proces.
        $project->phases()->limit(4)->update(['status' => PhaseStatus::Gereed]);
        $project->refresh()->load('phases');

        $this->assertSame('planning', ProjectTabs::nextStep($project)['tab']);
    }

    public function test_tabs_count_what_is_open(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $project = $this->project();

        $project->tasks()->create([
            'title' => 'Tegels bestellen',
            'status' => TaskStatus::TeDoen,
            'customer_id' => $project->customer_id,
        ]);

        $project->snags()->create([
            'title' => 'Kitnaad niet strak',
            'location' => 'Douchehoek',
            'status' => SnagItem::STATUS_OPEN,
        ]);

        $tabs = collect(ProjectTabs::forUser($admin, $project, 'overzicht'))->keyBy('key');

        $this->assertSame(1, $tabs['taken']['count']);
        $this->assertSame(1, $tabs['kwaliteit']['count']);
        $this->assertSame(0, $tabs['werkbonnen']['count']);
    }
}
