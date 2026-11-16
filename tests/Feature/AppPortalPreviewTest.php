<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Photo;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Het klantportaal zoals de klant het ziet, als eigen schermen in de app. */
class AppPortalPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function project(): Project
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory()->create(['name' => 'Fam. Bakker']), 'name' => 'Keuken Bakker', 'progress' => 40]);
        $project->phases()->where('position', 5)->update(['status' => PhaseStatus::WachtOpKlant]);
        $klant = User::factory()->create(['role' => UserRole::Klant, 'customer_id' => $project->customer_id, 'name' => 'J. Bakker']);
        $project->phases()->where('position', 0)->update(['client_approved_at' => now(), 'client_approved_by' => $klant->id]);
        Photo::factory()->create(['project_id' => $project->id]);
        Photo::factory()->intern()->create(['project_id' => $project->id]);
        ScheduleEntry::factory()->create(['project_id' => $project->id, 'date' => today()->addDay()->toDateString(), 'title' => 'Tegelzetter']);

        return $project;
    }

    public function test_the_list_shows_running_projects_with_account_and_photos(): void
    {
        $project = $this->project();
        $token = User::factory()->create(['role' => UserRole::Sales])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.portal-preview'))->assertOk()
            ->assertJsonPath('projects.0.id', $project->id)
            ->assertJsonPath('projects.0.has_account', true)
            ->assertJsonPath('projects.0.waiting', true)
            ->assertJsonPath('projects.0.sub', 'Fam. Bakker, voortgang 40%, 1 foto zichtbaar, 1 verborgen')
            ->assertJsonPath('projects.0.path', route('portal-preview.show', $project, false));
    }

    public function test_a_project_shows_what_the_customer_sees(): void
    {
        $project = $this->project();
        $token = User::factory()->create(['role' => UserRole::Admin])->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(route('api.app.portal-preview.show', $project))->assertOk()
            ->assertJsonPath('name', 'Keuken Bakker')
            ->assertJsonPath('customer', 'Fam. Bakker')
            ->assertJsonPath('progress', 40)
            ->assertJsonPath('photos.visible', 1)
            ->assertJsonPath('photos.hidden', 1)
            ->assertJsonCount(1, 'photos.items')
            ->assertJsonCount(1, 'waiting')
            ->assertJsonPath('approved.0.sub', 'J. Bakker, '.today()->translatedFormat('j M Y'))
            ->assertJsonPath('week.0.title', 'Tegelzetter')
            ->assertJsonPath('links.dossier', route('projects.show', $project, false));

        // De foto komt via het ondertekende adres van de app (geen sessiecookie).
        $this->assertStringStartsWith('/api/app/fotos/', $response->json('photos.items.0.url'));
    }

    public function test_executors_cannot_open_the_preview(): void
    {
        $project = $this->project();
        $token = User::factory()->create(['role' => UserRole::Uitvoerder])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.portal-preview'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.portal-preview.show', $project))->assertForbidden();
    }
}
