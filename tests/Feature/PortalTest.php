<?php

namespace Tests\Feature;

use App\Actions\GenerateDeliveryReport;
use App\Enums\PhaseStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Photo;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Project}
     */
    private function klantMetProject(): array
    {
        $customer = Customer::factory()->create(['name' => 'Familie Jansen']);
        $project = Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Verbouwing Huissen']);
        $klant = User::factory()->create(['role' => UserRole::Klant, 'customer_id' => $customer->id, 'name' => 'R. Jansen']);

        return [$klant, $project];
    }

    public function test_a_klant_is_redirected_from_the_dashboard_to_the_portal(): void
    {
        [$klant, $project] = $this->klantMetProject();

        $this->actingAs($klant)->get('/')->assertRedirect(route('portal.index'));

        // Eén project: portaal-index stuurt direct door naar het project.
        $this->actingAs($klant)->get('/portaal')->assertRedirect(route('portal.show', $project));
    }

    public function test_the_portal_shows_progress_timeline_and_this_week(): void
    {
        [$klant, $project] = $this->klantMetProject();

        $response = $this->actingAs($klant)->get('/portaal/projecten/'.$project->id);

        $response->assertOk();
        $response->assertSee('Verbouwing Huissen');
        $response->assertSee('gereed');
        $response->assertSee('Op schema');
        $response->assertSee('Voorbereiding');
        $response->assertSee('Oplevering');
        $response->assertSee('Deze week');
    }

    public function test_a_klant_cannot_see_someone_elses_project_or_internal_pages(): void
    {
        [$klant] = $this->klantMetProject();
        $anderProject = Project::factory()->create();

        $this->actingAs($klant)->get('/portaal/projecten/'.$anderProject->id)->assertForbidden();

        foreach (['/projecten', '/taken', '/planning', '/aanvragen', '/klanten', '/calculaties', '/aandacht'] as $url) {
            $this->actingAs($klant)->get($url)->assertForbidden();
        }
    }

    public function test_a_klant_sees_only_client_visible_photos(): void
    {
        [$klant, $project] = $this->klantMetProject();

        $zichtbaar = Photo::factory()->create(['project_id' => $project->id]);
        $intern = Photo::factory()->intern()->create(['project_id' => $project->id]);

        $this->actingAs($klant)->get('/fotos/'.$zichtbaar->id)->assertStatus(404); // bestand bestaat niet in test, maar toegang is ok
        $this->actingAs($klant)->get('/fotos/'.$intern->id)->assertForbidden();
    }

    public function test_a_klant_can_sign_gezien_en_akkoord_on_a_completed_phase(): void
    {
        [$klant, $project] = $this->klantMetProject();

        $fase = $project->phases()->firstWhere('position', 0);
        $fase->update(['status' => PhaseStatus::Gereed, 'completed_at' => now()]);

        $this->actingAs($klant)
            ->from(route('portal.show', $project))
            ->post('/portaal/fasen/'.$fase->id.'/akkoord')
            ->assertRedirect(route('portal.show', $project));

        $fase->refresh();
        $this->assertNotNull($fase->client_approved_at);
        $this->assertSame($klant->id, $fase->client_approved_by);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'project_phase', 'action' => 'klant_akkoord', 'source' => 'website']);

        // Nog een keer tekenen kan niet.
        $this->actingAs($klant)->post('/portaal/fasen/'.$fase->id.'/akkoord');
        $this->assertSame($fase->client_approved_at->timestamp, $fase->refresh()->client_approved_at->timestamp);
    }

    public function test_a_klant_can_approve_all_completed_phases_at_once(): void
    {
        [$klant, $project] = $this->klantMetProject();
        $fasen = $project->phases()->orderBy('position')->take(3)->get();
        $fasen->each->update(['status' => PhaseStatus::Gereed, 'completed_at' => now()]);
        $openFase = $project->phases()->where('status', '!=', PhaseStatus::Gereed)->first();

        $response = $this->actingAs($klant)->get('/portaal/projecten/'.$project->id);
        $response->assertSee('3 afgeronde fasen om te bevestigen');
        $response->assertSee('Alles gezien en akkoord');

        $this->actingAs($klant)->post('/portaal/projecten/'.$project->id.'/fasen-akkoord')
            ->assertSessionHas('success');

        foreach ($fasen as $fase) {
            $this->assertNotNull($fase->fresh()->client_approved_at);
        }
        $this->assertNull($openFase->fresh()->client_approved_at);
        $this->assertSame(3, AuditLog::where('action', 'klant_akkoord')->count());
    }

    public function test_a_klant_cannot_approve_phases_of_someone_elses_project(): void
    {
        [$klant] = $this->klantMetProject();
        $anderProject = Project::factory()->create();

        $this->actingAs($klant)->post('/portaal/projecten/'.$anderProject->id.'/fasen-akkoord')->assertForbidden();
    }

    public function test_a_waiting_delivery_report_has_a_button_in_the_portal(): void
    {
        [$klant, $project] = $this->klantMetProject();
        $report = (new GenerateDeliveryReport)->handle($project);

        $response = $this->actingAs($klant)->get('/portaal/projecten/'.$project->id);

        $response->assertSee('Bekijk rapport');
        $response->assertSee(route('portal.report', $report));
    }

    public function test_a_portal_photo_opens_in_the_viewer(): void
    {
        [$klant, $project] = $this->klantMetProject();
        $foto = Photo::factory()->create(['project_id' => $project->id]);

        $this->actingAs($klant)->get('/portaal/projecten/'.$project->id)
            ->assertSee('data-photo="'.route('photos.show', $foto).'"', false);
    }

    public function test_an_open_phase_cannot_be_signed(): void
    {
        [$klant, $project] = $this->klantMetProject();
        $fase = $project->phases()->firstWhere('position', 0);

        $this->actingAs($klant)->post('/portaal/fasen/'.$fase->id.'/akkoord');

        $this->assertNull($fase->refresh()->client_approved_at);
    }

    public function test_internal_users_cannot_open_the_portal(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/portaal')->assertForbidden();
    }

    public function test_a_portal_account_can_be_created_and_reset_from_the_customer_page(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create(['email' => 'jansen@example.nl']);

        $this->actingAs($admin)->post('/klanten/'.$customer->id.'/portaal');

        $portalUser = $customer->refresh()->portalUser;
        $this->assertNotNull($portalUser);
        $this->assertSame(UserRole::Klant, $portalUser->role);
        $this->assertSame('jansen@example.nl', $portalUser->email);

        $oudWachtwoord = $portalUser->password;
        $this->actingAs($admin)->post('/klanten/'.$customer->id.'/portaal');
        $this->assertNotSame($oudWachtwoord, $portalUser->refresh()->password);
    }

    public function test_the_team_module_cannot_create_klant_accounts(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->postJson('/team', [
            'name' => 'X', 'email' => 'x@test.nl', 'role' => 'klant', 'password' => 'wachtwoord123',
        ])->assertUnprocessable();

        $this->actingAs($admin)->get('/team')->assertDontSee('x@test.nl');
    }
}
