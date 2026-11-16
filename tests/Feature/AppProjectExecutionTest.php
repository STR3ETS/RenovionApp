<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\ProjectPhase;
use App\Models\QualityReview;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Uitvoering van een project in de app: fasen, gate en werkpakketten (tab "uitvoering" van het dossier). */
class AppProjectExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_office_sees_every_phase_and_can_change_and_release_it(): void
    {
        $package = WorkPackage::factory()->create(['name' => 'Elektra begane grond']);
        $project = $package->project;
        $phase = $package->phase;
        $token = User::factory()->create(['role' => UserRole::Admin])->createToken('test')->plainTextToken;

        $data = $this->withToken($token)->getJson(route('api.app.projects.execution', $project))->assertOk()
            ->assertJsonPath('project.path', route('projects.show', $project, false))
            ->assertJsonPath('can_manage', true)
            ->json();
        $this->assertCount($project->phases()->count(), $data['phases']);
        $uitvoering = collect($data['phases'])->firstWhere('id', $phase->id);
        $this->assertSame('Elektra begane grond', $uitvoering['packages'][0]['name']);
        $this->assertCount(count(PhaseStatus::cases()), $data['statuses']);

        // Vrijgeven kan pas als alle werkpakketten gereed zijn: dezelfde gate als op de website.
        $this->withToken($token)->postJson(route('api.app.phases.approve', $phase), ['gate_note' => 'Bewijs gezien'])
            ->assertUnprocessable()->assertJsonPath('message', fn ($message) => str_contains($message, 'Elektra begane grond'));

        $this->withToken($token)->patchJson(route('api.app.phases.update', $phase), ['status' => 'geblokkeerd'])
            ->assertOk()->assertJsonPath('message', 'Fase "'.$phase->name.'" bijgewerkt.');
        $this->assertSame(PhaseStatus::Geblokkeerd, $phase->fresh()->status);

        $package->update(['status' => PhaseStatus::Gereed, 'completed_at' => now()]);
        $this->withToken($token)->postJson(route('api.app.phases.approve', $phase), ['gate_note' => 'Alles af'])->assertOk();
        $this->assertSame(PhaseStatus::Gereed, $phase->fresh()->status);
        $this->assertSame('Alles af', $phase->fresh()->gate_note);
    }

    public function test_the_office_adds_a_work_package_and_goes_to_it(): void
    {
        $package = WorkPackage::factory()->create();
        $token = User::factory()->create(['role' => UserRole::Admin])->createToken('test')->plainTextToken;

        $created = $this->withToken($token)->postJson(route('api.app.work-packages.store', $package->project), [
            'project_phase_id' => $package->project_phase_id,
            'name' => 'Tegelwerk badkamer',
        ])->assertCreated()->json();

        $this->assertSame(route('work-packages.show', $created['id'], false), $created['path']);
        $this->assertDatabaseHas('work_packages', ['id' => $created['id'], 'name' => 'Tegelwerk badkamer']);

        $this->withToken($token)->postJson(route('api.app.work-packages.store', $package->project), ['project_phase_id' => $package->project_phase_id])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        // De checklist aanvullen, met een verplichte bewijsfoto.
        $this->withToken($token)->postJson(route('api.app.checklist-items.store', $created['id']), ['label' => 'Kitnaden', 'requires_photos' => 1])
            ->assertCreated()->assertJsonPath('message', 'Checklistitem toegevoegd.');
        $this->withToken($token)->getJson(route('api.app.work-packages.show', $created['id']))->assertOk()
            ->assertJsonPath('items.0.label', 'Kitnaden')
            ->assertJsonPath('items.0.requires_photos', 1);
    }

    public function test_the_office_sets_the_project_status_and_finds_the_delivery_report_in_quality(): void
    {
        $package = WorkPackage::factory()->create();
        $project = $package->project;
        $token = User::factory()->create(['role' => UserRole::Admin])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.projects.show', $project))->assertOk()
            ->assertJsonPath('status_value', $project->status->value)
            ->assertJsonCount(count(ProjectStatus::cases()), 'statuses');

        $this->withToken($token)->patchJson(route('api.app.projects.status', $project), ['status' => 'afgerond'])
            ->assertOk()->assertJsonPath('label', 'Afgerond');
        $this->assertSame(ProjectStatus::Afgerond, $project->fresh()->status);
        $this->assertSame(100, (int) $project->fresh()->progress);

        // Kwaliteit: eerst geen rapport, na opmaken staat het er met een pad.
        $this->withToken($token)->getJson(route('api.app.quality.show', $project))->assertOk()->assertJsonPath('report', null);
        $path = $this->withToken($token)->postJson(route('api.app.delivery-reports.store', $project))->assertOk()->json('path');
        $this->withToken($token)->getJson(route('api.app.quality.show', $project))->assertOk()->assertJsonPath('report.path', $path);
    }

    public function test_the_aftercare_phase_does_not_count_towards_progress(): void
    {
        $project = WorkPackage::factory()->gereed()->create()->project;
        $project->phases()->where('name', '!=', ProjectPhase::AFTERCARE)->update(['status' => PhaseStatus::Gereed]);
        $project->syncProgress();

        // Alles tot en met de oplevering vrijgegeven: 100%, ook al loopt de nazorg nog.
        $this->assertSame(100, (int) $project->fresh()->progress);
        $this->assertNotSame(PhaseStatus::Gereed, $project->phases()->where('name', ProjectPhase::AFTERCARE)->value('status'));
        // De kwaliteitsketen kan dan beginnen (geen 95%-blokkade meer).
        $this->assertSame('', QualityReview::forProject($project->fresh())->blockerFor(0));
    }

    public function test_a_craftsman_on_the_project_can_look_but_not_manage(): void
    {
        $package = WorkPackage::factory()->create();
        $craftsman = User::factory()->uitvoerder()->create();
        $package->project->craftsmen()->attach($craftsman);
        $token = $craftsman->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.projects.execution', $package->project))->assertOk()
            ->assertJsonPath('can_manage', false)
            ->assertJsonPath('statuses', []);
        $this->withToken($token)->patchJson(route('api.app.phases.update', $package->phase), ['status' => 'bezig'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $stranger = User::factory()->uitvoerder()->create()->createToken('test')->plainTextToken;
        $this->withToken($stranger)->getJson(route('api.app.projects.execution', $package->project))->assertForbidden();
    }
}
