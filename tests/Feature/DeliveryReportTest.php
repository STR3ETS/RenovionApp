<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\DeliveryReport;
use App\Models\Photo;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryReportTest extends TestCase
{
    use RefreshDatabase;

    private function projectMetWerk(): Project
    {
        $project = Project::factory()->create([
            'name' => 'Verbouwing Huissen',
            'start_date' => today()->subDays(30)->toDateString(),
            'end_date_expected' => today()->subDays(2)->toDateString(),
        ]);

        $package = WorkPackage::factory()->gereed()->create(['project_id' => $project->id, 'project_phase_id' => $project->phases()->firstWhere('name', 'Uitvoering')->id, 'name' => 'Elektra begane grond']);
        $package->items()->create(['label' => 'Eindcontrole + testen', 'done_at' => now(), 'position' => 1]);
        Photo::factory()->create(['project_id' => $project->id, 'work_package_id' => $package->id]);

        WorkPackage::factory()->create(['project_id' => $project->id, 'project_phase_id' => $package->project_phase_id, 'name' => 'Kitwerk sanitair', 'deadline' => today()->addDays(3)->toDateString()]);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'Restpunt: plintje vervangen']);

        return $project;
    }

    public function test_a_report_is_generated_from_project_data(): void
    {
        $user = User::factory()->create();
        $project = $this->projectMetWerk();

        $response = $this->actingAs($user)->post('/projecten/'.$project->id.'/opleverrapport');

        $report = $project->refresh()->deliveryReport;
        $response->assertRedirect(route('delivery-reports.show', $report));

        $snapshot = $report->snapshot;
        $this->assertSame('Verbouwing Huissen', $snapshot['project']['name']);
        $this->assertGreaterThan(0, $snapshot['project']['deviation_days']);
        $this->assertCount(9, $snapshot['phases']);
        $this->assertSame('Elektra begane grond', $snapshot['work'][0]['name']);
        $this->assertCount(1, $snapshot['work'][0]['photo_ids']);

        $restpunten = collect($snapshot['leftovers']);
        $this->assertTrue($restpunten->contains('title', 'Kitwerk sanitair'));
        $this->assertTrue($restpunten->contains('title', 'Restpunt: plintje vervangen'));

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'delivery_report', 'action' => 'aangemaakt']);
        $this->assertDatabaseHas('timeline_events', ['subject_type' => 'delivery_report', 'customer_id' => $project->customer_id]);

        $this->actingAs($user)->get('/opleverrapporten/'.$report->id)
            ->assertOk()
            ->assertSee('Opleverrapport')
            ->assertSee('Restpunten')
            ->assertSee('Kitwerk sanitair');
    }

    public function test_regenerating_updates_the_same_report_until_the_client_signs(): void
    {
        $user = User::factory()->create();
        $project = $this->projectMetWerk();

        $this->actingAs($user)->post('/projecten/'.$project->id.'/opleverrapport');
        $report = $project->refresh()->deliveryReport;

        $this->actingAs($user)->post('/projecten/'.$project->id.'/opleverrapport');
        $this->assertSame(1, DeliveryReport::count());

        $report->update(['client_signed_name' => 'R. Jansen', 'client_signed_at' => now(), 'client_signed_ip' => '127.0.0.1']);
        $generatedAt = $report->refresh()->generated_at;

        $this->actingAs($user)->post('/projecten/'.$project->id.'/opleverrapport');
        $this->assertSame($generatedAt->timestamp, $report->refresh()->generated_at->timestamp);
    }

    public function test_renovion_can_sign_internally(): void
    {
        $user = User::factory()->create(['name' => 'Imad']);
        $project = $this->projectMetWerk();
        $this->actingAs($user)->post('/projecten/'.$project->id.'/opleverrapport');
        $report = $project->refresh()->deliveryReport;

        $this->actingAs($user)->post('/opleverrapporten/'.$report->id.'/ondertekenen');

        $report->refresh();
        $this->assertNotNull($report->company_signed_at);
        $this->assertSame($user->id, $report->company_signed_by);
    }

    public function test_the_client_signs_via_the_portal(): void
    {
        $admin = User::factory()->create();
        $project = $this->projectMetWerk();
        $klant = User::factory()->create(['role' => UserRole::Klant, 'customer_id' => $project->customer_id]);

        $this->actingAs($admin)->post('/projecten/'.$project->id.'/opleverrapport');
        $report = $project->refresh()->deliveryReport;

        // Rapport staat als actie in het portaal.
        $this->actingAs($klant)->get('/portaal/projecten/'.$project->id)
            ->assertOk()
            ->assertSee('Opleverrapport staat voor u klaar');

        $this->actingAs($klant)->get('/portaal/opleverrapporten/'.$report->id)
            ->assertOk()
            ->assertSee('Rapport ondertekenen');

        $this->actingAs($klant)->post('/portaal/opleverrapporten/'.$report->id.'/ondertekenen', [
            'signed_name' => 'R. Jansen',
            'agree' => '1',
        ]);

        $report->refresh();
        $this->assertSame('R. Jansen', $report->client_signed_name);
        $this->assertNotNull($report->client_signed_at);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'delivery_report', 'action' => 'ondertekend_klant', 'source' => 'website']);
    }

    public function test_other_clients_cannot_see_or_sign_the_report(): void
    {
        $admin = User::factory()->create();
        $project = $this->projectMetWerk();
        $this->actingAs($admin)->post('/projecten/'.$project->id.'/opleverrapport');
        $report = $project->refresh()->deliveryReport;

        $andereKlant = User::factory()->create(['role' => UserRole::Klant, 'customer_id' => Customer::factory()->create()->id]);

        $this->actingAs($andereKlant)->get('/portaal/opleverrapporten/'.$report->id)->assertForbidden();
        $this->actingAs($andereKlant)->post('/portaal/opleverrapporten/'.$report->id.'/ondertekenen', ['signed_name' => 'X', 'agree' => '1'])->assertForbidden();
    }

    public function test_uitvoerders_cannot_generate_reports(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $project = $this->projectMetWerk();
        $project->craftsmen()->attach($uitvoerder);

        $this->actingAs($uitvoerder)->post('/projecten/'.$project->id.'/opleverrapport')->assertForbidden();
    }
}
