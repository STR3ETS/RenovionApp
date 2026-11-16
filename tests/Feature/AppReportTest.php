<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use App\Services\ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rapportages en marketing als eigen schermen in de app. */
class AppReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_report_type_opens_and_a_project_report_needs_a_project(): void
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory()->create(['name' => 'Fam. Jansen']), 'name' => 'Badkamer Jansen']);
        $token = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Raphael'])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.reports.index'))->assertOk()
            ->assertJsonPath('type', 'week')
            ->assertJsonPath('types.*.value', array_keys(ReportBuilder::TYPES))
            ->assertJsonPath('projects', [])
            ->assertJsonPath('report.title', 'Weekrapport week '.today()->startOfWeek()->isoWeek());

        foreach (array_keys(ReportBuilder::TYPES) as $type) {
            $this->withToken($token)->getJson(route('api.app.reports.index', ['type' => $type]))->assertOk()
                ->assertJsonPath('type', $type)
                ->assertJsonStructure(['report' => ['title', 'period', 'kpis', 'sections'], 'made']);
        }

        $this->withToken($token)->getJson(route('api.app.reports.index', ['type' => 'project']))->assertOk()
            ->assertJsonPath('projects.0.label', 'Badkamer Jansen, Fam. Jansen')
            ->assertJsonPath('report.sections.0.title', 'Geen project gekozen');

        $this->withToken($token)->getJson(route('api.app.reports.index', ['type' => 'project', 'project' => $project->id]))->assertOk()
            ->assertJsonPath('project', $project->id)
            ->assertJsonPath('report.title', 'Projectrapport Badkamer Jansen');
    }

    public function test_marketing_shows_conversion_per_source_and_twelve_months(): void
    {
        $customer = Customer::factory()->create();
        Lead::factory()->count(2)->create(['customer_id' => $customer->id, 'campaign' => 'Google', 'status' => LeadStatus::Project, 'value' => 15000]);
        Lead::factory()->create(['customer_id' => $customer->id, 'campaign' => 'Google', 'status' => LeadStatus::Verloren]);
        $token = User::factory()->create(['role' => UserRole::Sales])->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(route('api.app.marketing.index', ['periode' => 'alles']))->assertOk()
            ->assertJsonPath('period', 'alles')
            ->assertJsonPath('periods.*.value', ['jaar', 'kwartaal', '90', 'alles'])
            ->assertJsonPath('kpis.total', 3)
            ->assertJsonPath('kpis.won_value', 30000)
            ->assertJsonPath('best_source.label', 'Google')
            ->assertJsonPath('best_source.conversion', '66,7')
            ->assertJsonCount(12, 'per_month')
            ->assertJsonPath('breakdowns.*.key', ['sources', 'channels', 'services']);

        $google = collect($response->json('breakdowns.0.rows'))->firstWhere('label', 'Google');
        $this->assertSame(['label' => 'Google', 'total' => 3, 'won' => 2, 'conversion' => '66,7%', 'tone' => 'green', 'value' => 30000], $google);
    }

    public function test_executors_cannot_open_reports(): void
    {
        $token = User::factory()->create(['role' => UserRole::Uitvoerder])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.reports.index'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.marketing.index'))->assertForbidden();
    }
}
