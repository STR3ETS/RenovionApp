<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Models\Photo;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_craftsman_can_upload_photos_to_a_work_package(): void
    {
        Storage::fake('local');

        $uitvoerder = User::factory()->uitvoerder()->create();
        $package = WorkPackage::factory()->create();
        $package->project->craftsmen()->attach($uitvoerder);

        $this->actingAs($uitvoerder)->post('/projecten/'.$package->project_id.'/fotos', [
            'photos' => [UploadedFile::fake()->image('leidingwerk.jpg'), UploadedFile::fake()->image('elektra.jpg')],
            'work_package_id' => $package->id,
            'caption' => 'Leidingwerk badkamer',
        ]);

        $this->assertSame(2, $package->photos()->count());

        $photo = $package->photos()->first();
        $this->assertSame($package->project_phase_id, $photo->project_phase_id);
        $this->assertSame($uitvoerder->id, $photo->uploaded_by);
        Storage::assertExists($photo->path);

        $this->actingAs($uitvoerder)->get('/fotos/'.$photo->id)->assertOk();
        $this->assertDatabaseHas('timeline_events', ['customer_id' => $package->project->customer_id, 'type' => 'document']);
    }

    public function test_uitvoerders_cannot_upload_or_view_on_foreign_projects(): void
    {
        Storage::fake('local');

        $uitvoerder = User::factory()->uitvoerder()->create();
        $project = Project::factory()->create();
        $photo = Photo::factory()->create(['project_id' => $project->id]);

        $this->actingAs($uitvoerder)->post('/projecten/'.$project->id.'/fotos', [
            'photos' => [UploadedFile::fake()->image('x.jpg')],
        ])->assertForbidden();

        $this->actingAs($uitvoerder)->get('/fotos/'.$photo->id)->assertForbidden();
    }

    public function test_a_checklist_item_with_photo_requirement_cannot_be_checked_without_evidence(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $package = WorkPackage::factory()->create();
        $item = $package->items()->create(['label' => "Foto's vóór dichtzetten", 'requires_photos' => 2, 'position' => 1]);

        $this->actingAs($user)->patch('/werkpakketten/'.$package->id.'/items/'.$item->id.'/toggle');
        $this->assertFalse($item->refresh()->isDone());

        $this->actingAs($user)->post('/projecten/'.$package->project_id.'/fotos', [
            'photos' => [UploadedFile::fake()->image('een.jpg'), UploadedFile::fake()->image('twee.jpg')],
            'work_package_id' => $package->id,
            'checklist_item_id' => $item->id,
        ]);

        $this->actingAs($user)->patch('/werkpakketten/'.$package->id.'/items/'.$item->id.'/toggle');
        $this->assertTrue($item->refresh()->isDone());
    }

    public function test_a_work_package_cannot_complete_without_required_photo_evidence(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $package = WorkPackage::factory()->create();
        $item = $package->items()->create([
            'label' => 'Leidingwerk vastleggen',
            'requires_photos' => 1,
            'position' => 1,
            'done_at' => now(),
            'done_by' => $user->id,
        ]);

        $this->actingAs($user)->post('/werkpakketten/'.$package->id.'/afronden');
        $this->assertNotSame(PhaseStatus::Gereed, $package->refresh()->status);

        Photo::factory()->create([
            'project_id' => $package->project_id,
            'work_package_id' => $package->id,
            'checklist_item_id' => $item->id,
        ]);

        $this->actingAs($user)->post('/werkpakketten/'.$package->id.'/afronden');
        $this->assertSame(PhaseStatus::Gereed, $package->refresh()->status);
    }

    public function test_the_cover_photo_falls_back_to_the_latest_client_visible_photo(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->create(['cover_photo_path' => null]);

        $this->actingAs($user)->get('/projecten/'.$project->id.'/omslagfoto')->assertNotFound();

        $path = UploadedFile::fake()->image('voortgang.jpg')->store('project-photos');
        Photo::factory()->intern()->create(['project_id' => $project->id, 'path' => $path]);

        // Interne foto's zijn geen omslag.
        $this->actingAs($user)->get('/projecten/'.$project->id.'/omslagfoto')->assertNotFound();

        Photo::factory()->create(['project_id' => $project->id, 'path' => $path]);
        $this->actingAs($user)->get('/projecten/'.$project->id.'/omslagfoto')->assertOk();
    }

    public function test_visibility_can_be_toggled_and_uploader_can_delete(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create();
        $uitvoerder = User::factory()->uitvoerder()->create();
        $project = Project::factory()->create();
        $project->craftsmen()->attach($uitvoerder);

        $path = UploadedFile::fake()->image('detail.jpg')->store('project-photos');
        $photo = Photo::factory()->create(['project_id' => $project->id, 'path' => $path, 'uploaded_by' => $uitvoerder->id]);

        $this->actingAs($admin)->patch('/fotos/'.$photo->id, ['client_visible' => false]);
        $this->assertFalse($photo->refresh()->client_visible);

        // Uitvoerders mogen de zichtbaarheid niet beheren, wel hun eigen foto verwijderen.
        $this->actingAs($uitvoerder)->patch('/fotos/'.$photo->id, ['client_visible' => true])->assertForbidden();

        $this->actingAs($uitvoerder)->delete('/fotos/'.$photo->id);
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        Storage::assertMissing($path);
    }
}
