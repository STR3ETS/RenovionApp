<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppWorkPackageTest extends TestCase
{
    use RefreshDatabase;

    private function craftsmanOn(WorkPackage $package): User
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $package->project->craftsmen()->attach($uitvoerder);

        return $uitvoerder;
    }

    public function test_the_project_file_lists_work_packages_per_phase(): void
    {
        $package = WorkPackage::factory()->create(['name' => 'Elektra begane grond']);
        $package->items()->create(['label' => 'Groepenkast', 'done_at' => now()]);
        $package->items()->create(['label' => 'Wandcontactdozen']);

        $work = $this->withToken($this->craftsmanOn($package)->createToken('test')->plainTextToken)
            ->getJson(route('api.app.projects.show', $package->project))
            ->assertOk()
            ->json('work');

        $this->assertCount(1, $work);
        $this->assertSame('Elektra begane grond', $work[0]['packages'][0]['name']);
        $this->assertSame([2, 1], [$work[0]['packages'][0]['items'], $work[0]['packages'][0]['items_done']]);
    }

    public function test_a_craftsman_ticks_off_photographs_and_completes_from_the_app(): void
    {
        Storage::fake('local');
        $package = WorkPackage::factory()->create();
        $item = $package->items()->create(['label' => 'Leidingwerk', 'requires_photos' => 1]);
        $token = $this->craftsmanOn($package)->createToken('test')->plainTextToken;

        // Zonder bewijsfoto kan het niet af, met een Nederlandse melding.
        $this->withToken($token)->patchJson(route('api.app.checklist-items.toggle', [$package, $item]))
            ->assertUnprocessable()
            ->assertJsonPath('message', '"Leidingwerk" vereist nog 1 bewijsfoto - upload die eerst.');

        $photos = $this->withToken($token)->post(route('api.app.work-packages.photos', $package), [
            'photos' => [UploadedFile::fake()->image('leiding.jpg')],
            'checklist_item_id' => $item->id,
        ], ['Accept' => 'application/json'])->assertCreated()->json('photos');

        $this->assertCount(1, $photos);
        $this->assertStringStartsWith('/api/app/fotos/', $photos[0]['url']);
        $this->get($photos[0]['url'])->assertOk();
        $this->get(strtok($photos[0]['url'], '?'))->assertForbidden();

        $this->withToken($token)->patchJson(route('api.app.checklist-items.toggle', [$package, $item]))
            ->assertOk()
            ->assertJsonPath('items.0.done', true)
            ->assertJsonPath('checklist_complete', true);

        $this->withToken($token)->postJson(route('api.app.work-packages.complete', $package))
            ->assertOk()
            ->assertJsonPath('done', true);
        $this->assertSame(PhaseStatus::Gereed, $package->fresh()->status);

        // Afgerond: afvinken kan pas weer na heropenen.
        $this->withToken($token)->patchJson(route('api.app.checklist-items.toggle', [$package, $item]))->assertUnprocessable();
        $this->withToken($token)->postJson(route('api.app.work-packages.reopen', $package))->assertOk()->assertJsonPath('done', false);
    }

    public function test_completing_needs_a_complete_checklist(): void
    {
        $package = WorkPackage::factory()->create();
        $package->items()->create(['label' => 'Opruimen']);

        $this->withToken($this->craftsmanOn($package)->createToken('test')->plainTextToken)
            ->postJson(route('api.app.work-packages.complete', $package))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Nog niet alle checklistitems zijn afgevinkt - rond eerst de checklist af.');
    }

    public function test_a_craftsman_cannot_open_work_packages_of_other_projects(): void
    {
        $package = WorkPackage::factory()->create();
        $other = User::factory()->uitvoerder()->create();

        $this->withToken($other->createToken('test')->plainTextToken)
            ->getJson(route('api.app.work-packages.show', $package))
            ->assertForbidden();
    }
}
