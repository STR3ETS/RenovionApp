<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Photo;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** De Foto's-tab van een project en één foto schermvullend, als eigen schermen in de app. */
class AppPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function tokenFor(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken('test')->plainTextToken;
    }

    public function test_a_craftsman_sees_and_adds_photos_on_his_own_project_only(): void
    {
        $craftsman = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $project = Project::factory()->create();
        $project->craftsmen()->attach($craftsman);
        $phase = $project->phases()->orderBy('position')->first();
        Photo::factory()->create(['project_id' => $project->id, 'project_phase_id' => $phase->id, 'caption' => 'Leidingwerk', 'uploaded_by' => $craftsman->id]);
        Photo::factory()->intern()->create(['project_id' => $project->id, 'caption' => null]);
        $token = $this->tokenFor($craftsman);

        $this->withToken($token)->getJson(route('api.app.projects.photos', Project::factory()->create()))->assertForbidden();

        $response = $this->withToken($token)->getJson(route('api.app.projects.photos', $project))->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('phases', [['id' => $phase->id, 'name' => $phase->name, 'count' => 1]])
            ->assertJsonPath('current_phase_id', $phase->id)
            ->assertJsonCount(9, 'upload_phases');
        $photos = collect($response->json('photos'));
        $this->assertTrue($photos->firstWhere('caption', 'Leidingwerk')['removable']);
        $this->assertSame([true, false], [$photos->firstWhere('caption', 'Projectfoto')['internal'], $photos->firstWhere('caption', 'Projectfoto')['removable']]);
        $this->assertStringStartsWith('/api/app/fotos/', $photos->first()['url']);

        $this->withToken($token)->post(route('api.app.projects.photos.store', $project), [
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            'project_phase_id' => $phase->id,
            'caption' => 'Tegelwerk',
            'client_visible' => '0',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('message', "2 foto's geüpload.");

        $this->assertSame(2, $project->photos()->where('caption', 'Tegelwerk')->where('client_visible', false)->where('uploaded_by', $craftsman->id)->count());
        $this->withToken($token)->post(route('api.app.projects.photos.store', $project), ['photos' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_the_viewer_steps_through_the_project_and_only_the_office_edits(): void
    {
        $craftsman = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $project = Project::factory()->create(['name' => 'Badkamer Arnhem']);
        $project->craftsmen()->attach($craftsman);
        $oldest = Photo::factory()->create(['project_id' => $project->id, 'created_at' => now()->subDays(2)]);
        $middle = Photo::factory()->create(['project_id' => $project->id, 'created_at' => now()->subDay(), 'caption' => 'Voor']);
        $newest = Photo::factory()->create(['project_id' => $project->id, 'created_at' => now(), 'uploaded_by' => $craftsman->id]);
        $token = $this->tokenFor($craftsman);

        $this->withToken($token)->getJson(route('api.app.photos.show', $middle))->assertOk()
            ->assertJsonPath('caption', 'Voor')
            ->assertJsonPath('position', 2)
            ->assertJsonPath('count', 3)
            ->assertJsonPath('previous', $newest->id)
            ->assertJsonPath('next', $oldest->id)
            ->assertJsonPath('removable', false)
            ->assertJsonPath('editable', false)
            ->assertJsonPath('project.path', route('projects.show', $project, false));
        $this->withToken($token)->getJson(route('api.app.photos.show', $newest))->assertOk()->assertJsonPath('previous', null)->assertJsonPath('removable', true);
        $this->withToken($token)->patchJson(route('api.app.photos.update', $middle), ['client_visible' => false])->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.photos.show', Photo::factory()->create()))->assertForbidden();

        $admin = $this->tokenFor(User::factory()->create(['role' => UserRole::Admin]));
        $this->withToken($admin)->getJson(route('api.app.photos.show', $middle))->assertOk()->assertJsonPath('editable', true)->assertJsonPath('removable', true);
        $this->withToken($admin)->patchJson(route('api.app.photos.update', $middle), ['client_visible' => false])->assertOk()->assertJsonPath('client_visible', false);
        $this->withToken($admin)->patchJson(route('api.app.photos.update', $middle), ['caption' => 'Na de sloop'])->assertOk();
        $this->assertSame(['Na de sloop', false], [$middle->fresh()->caption, $middle->fresh()->client_visible]);
        $this->withToken($admin)->getJson(route('api.app.photos.show', $middle))->assertOk()->assertJsonPath('internal', true)->assertJsonPath('raw_caption', 'Na de sloop');

        // Verwijderen gaat via de bestaande route van de app.
        $this->withToken($admin)->deleteJson(route('api.app.photos.destroy', $middle))->assertOk();
        $this->assertModelMissing($middle);
    }
}
