<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_work_package_can_be_created_in_a_phase(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $phase = $project->phases()->firstWhere('name', 'Uitvoering');

        $this->actingAs($user)->post('/projecten/'.$project->id.'/werkpakketten', [
            'project_phase_id' => $phase->id,
            'name' => 'Elektra begane grond',
            'deadline' => today()->addDays(5)->toDateString(),
        ]);

        $this->assertDatabaseHas('work_packages', [
            'project_id' => $project->id,
            'project_phase_id' => $phase->id,
            'name' => 'Elektra begane grond',
            'status' => 'niet_gestart',
        ]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'work_package', 'action' => 'aangemaakt']);
    }

    public function test_a_phase_from_another_project_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $anderProject = Project::factory()->create();

        $this->actingAs($user)->postJson('/projecten/'.$project->id.'/werkpakketten', [
            'project_phase_id' => $anderProject->phases()->first()->id,
            'name' => 'X',
        ])->assertUnprocessable();
    }

    public function test_checklist_items_can_be_added_and_toggled_by_a_craftsman(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $admin = User::factory()->create();
        $package = WorkPackage::factory()->create();
        $package->project->craftsmen()->attach($uitvoerder);

        $this->actingAs($admin)->post('/werkpakketten/'.$package->id.'/items', [
            'label' => "Foto's vóór dichtzetten",
            'requires_photos' => 3,
        ]);
        $this->assertSame(3, $package->items()->firstWhere('label', "Foto's vóór dichtzetten")->requires_photos);

        $this->actingAs($admin)->post('/werkpakketten/'.$package->id.'/items', ['label' => 'Freeswerk uitvoeren']);
        $item = $package->items()->firstWhere('label', 'Freeswerk uitvoeren');

        $this->actingAs($uitvoerder)
            ->patch('/werkpakketten/'.$package->id.'/items/'.$item->id.'/toggle')
            ->assertRedirect(route('work-packages.show', $package));

        $item->refresh();
        $this->assertTrue($item->isDone());
        $this->assertSame($uitvoerder->id, $item->done_by);
    }

    public function test_a_work_package_cannot_complete_with_open_checklist_items(): void
    {
        $user = User::factory()->create();
        $package = WorkPackage::factory()->create();
        $package->items()->create(['label' => 'Eindcontrole', 'position' => 1]);

        $this->actingAs($user)->post('/werkpakketten/'.$package->id.'/afronden');

        $this->assertSame(PhaseStatus::Bezig, $package->refresh()->status);
    }

    public function test_completing_a_work_package_updates_project_progress(): void
    {
        $user = User::factory()->create();
        $package = WorkPackage::factory()->create();
        $item = $package->items()->create(['label' => 'Eindcontrole', 'position' => 1]);

        $this->actingAs($user)->patch('/werkpakketten/'.$package->id.'/items/'.$item->id.'/toggle');
        $this->actingAs($user)->post('/werkpakketten/'.$package->id.'/afronden');

        $package->refresh();
        $this->assertSame(PhaseStatus::Gereed, $package->status);
        $this->assertNotNull($package->completed_at);

        // 1 fase (Uitvoering) volledig aan werkpakketten gereed = 1/9 fasen ≈ 11%.
        $this->assertSame(11, $package->project->refresh()->progress);
        $this->assertDatabaseHas('timeline_events', [
            'subject_type' => 'work_package',
            'subject_id' => $package->id,
        ]);
    }

    public function test_a_phase_gate_requires_all_work_packages_done(): void
    {
        $user = User::factory()->create();
        $package = WorkPackage::factory()->create();
        $phase = $package->phase;

        $this->actingAs($user)->post('/fasen/'.$phase->id.'/vrijgeven');
        $this->assertNotSame(PhaseStatus::Gereed, $phase->refresh()->status);

        $package->update(['status' => PhaseStatus::Gereed, 'completed_at' => now()]);

        $this->actingAs($user)->post('/fasen/'.$phase->id.'/vrijgeven', ['gate_note' => 'Alles gecontroleerd']);

        $phase->refresh();
        $this->assertSame(PhaseStatus::Gereed, $phase->status);
        $this->assertSame($user->id, $phase->approved_by);
        $this->assertSame('Alles gecontroleerd', $phase->gate_note);

        // De volgende niet-gestarte fase start automatisch.
        $next = $phase->project->phases()->where('position', '>', $phase->position)->orderBy('position')->first();
        $this->assertSame(PhaseStatus::Bezig, $next->status);

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'project_phase', 'action' => 'gate_vrijgegeven']);
    }

    public function test_a_phase_can_wait_or_be_blocked(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $phase = $project->phases()->firstWhere('position', 0);

        $this->actingAs($user)->patch('/fasen/'.$phase->id, ['status' => 'wacht_op_klant']);
        $this->assertSame(PhaseStatus::WachtOpKlant, $phase->refresh()->status);

        $this->actingAs($user)->patch('/fasen/'.$phase->id, ['status' => 'geblokkeerd']);
        $this->assertSame(PhaseStatus::Geblokkeerd, $phase->refresh()->status);

        $response = $this->actingAs($user)->get('/aandacht');
        $response->assertSee('Fase geblokkeerd');
    }

    public function test_uitvoerders_only_reach_work_packages_on_their_own_projects(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $package = WorkPackage::factory()->create();

        $this->actingAs($uitvoerder)->get('/werkpakketten/'.$package->id)->assertForbidden();
        $this->actingAs($uitvoerder)->post('/werkpakketten/'.$package->id.'/afronden')->assertForbidden();

        $package->project->craftsmen()->attach($uitvoerder);

        $this->actingAs($uitvoerder)->get('/werkpakketten/'.$package->id)
            ->assertOk()
            ->assertSee('Taak afronden');
    }

    public function test_uitvoerders_cannot_manage_phases_or_work_packages(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $package = WorkPackage::factory()->create();
        $package->project->craftsmen()->attach($uitvoerder);

        $this->actingAs($uitvoerder)->patch('/fasen/'.$package->phase->id, ['status' => 'gereed'])->assertForbidden();
        $this->actingAs($uitvoerder)->delete('/werkpakketten/'.$package->id)->assertForbidden();
        $this->actingAs($uitvoerder)->post('/projecten/'.$package->project_id.'/werkpakketten', [
            'project_phase_id' => $package->project_phase_id, 'name' => 'X',
        ])->assertForbidden();
    }
}
