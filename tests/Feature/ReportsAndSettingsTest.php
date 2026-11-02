<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\MarketingInsights;
use App\Services\ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_week_report_flags_what_is_not_going_well(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder, 'name' => 'Klaas Bakker']);
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'name' => 'Badkamer Jansen',
            'end_date_expected' => today()->subWeek(),
            'progress' => 70,
        ]);

        WorkOrder::create([
            'number' => WorkOrder::nextNumber(),
            'project_id' => $project->id,
            'user_id' => $uitvoerder->id,
            'title' => 'Tegelwerk',
            'date' => today(),
            'status' => WorkOrderStatus::Goedgekeurd,
            'hours' => 16,
            'approved_at' => now(),
        ]);

        WorkOrder::create([
            'number' => WorkOrder::nextNumber(),
            'project_id' => $project->id,
            'user_id' => $uitvoerder->id,
            'title' => 'Sanitair',
            'date' => today(),
            'status' => WorkOrderStatus::Controle,
            'hours' => 8,
        ]);

        $report = (new ReportBuilder)->build('week');

        $this->assertSame('week', $report['type']);

        $pending = collect($report['kpis'])->firstWhere('label', 'Werkbonnen ter controle');
        $this->assertSame('1', $pending['value']);
        $this->assertTrue($pending['bad']);

        $behind = collect($report['kpis'])->firstWhere('label', 'Projecten over de datum');
        $this->assertSame('1', $behind['value']);
        $this->assertTrue($behind['bad']);

        // De uren staan per medewerker, de achterstand als slechte regel.
        $hours = collect($report['sections'])->firstWhere('title', 'Geboekte uren per medewerker');
        $this->assertStringContainsString('Klaas Bakker: 16 uur', $hours['lines'][0]['text']);

        $late = collect($report['sections'])->firstWhere('title', 'Projecten over de opleverdatum');
        $this->assertTrue($late['lines'][0]['bad']);
        $this->assertStringContainsString('Badkamer Jansen', $late['lines'][0]['text']);
    }

    public function test_the_sales_report_counts_hit_rate_over_decided_leads_only(): void
    {
        $customer = Customer::factory()->create();

        // Drie gewonnen, één verloren, twee nog open: hitrate 75 procent.
        Lead::factory()->count(3)->create(['customer_id' => $customer->id, 'status' => LeadStatus::Project]);
        Lead::factory()->create(['customer_id' => $customer->id, 'status' => LeadStatus::Verloren, 'lost_reason' => 'Te duur']);
        Lead::factory()->count(2)->create(['customer_id' => $customer->id, 'status' => LeadStatus::Contact, 'next_action_at' => null]);

        $report = (new ReportBuilder)->build('sales');

        $this->assertSame('75,0%', collect($report['kpis'])->firstWhere('label', 'Hitrate')['value']);
        $this->assertSame('2', collect($report['kpis'])->firstWhere('label', 'Open aanvragen')['value']);

        $silent = collect($report['kpis'])->firstWhere('label', 'Zonder vervolgstap');
        $this->assertSame('2', $silent['value']);
        $this->assertTrue($silent['bad']);

        $reasons = collect($report['sections'])->firstWhere('title', 'Verloren met reden');
        $this->assertStringContainsString('Te duur: 1', $reasons['lines'][0]['text']);
    }

    public function test_marketing_measures_conversion_over_decided_leads(): void
    {
        $customer = Customer::factory()->create();

        // Google: twee gewonnen, één verloren. Beurs: nul van twee.
        Lead::factory()->count(2)->create([
            'customer_id' => $customer->id, 'campaign' => 'Google', 'status' => LeadStatus::Project, 'value' => 15000,
        ]);
        Lead::factory()->create(['customer_id' => $customer->id, 'campaign' => 'Google', 'status' => LeadStatus::Verloren]);
        Lead::factory()->count(2)->create(['customer_id' => $customer->id, 'campaign' => 'Beurs', 'status' => LeadStatus::Verloren]);
        // Een open aanvraag drukt de conversie niet: die kan nog beide kanten op.
        Lead::factory()->create(['customer_id' => $customer->id, 'campaign' => 'Google', 'status' => LeadStatus::Contact]);

        $data = (new MarketingInsights)->build('alles');

        $google = $data['sources']->firstWhere('label', 'Google');

        $this->assertSame(4, $google['total']);
        $this->assertSame(2, $google['won']);
        $this->assertSame(1, $google['open']);
        $this->assertSame(66.7, $google['conversion']);
        $this->assertSame(30000.0, $google['value']);
        $this->assertSame(15000.0, $google['average']);

        $beurs = $data['sources']->firstWhere('label', 'Beurs');
        $this->assertSame(0.0, $beurs['conversion']);
    }

    public function test_the_best_source_needs_at_least_three_leads(): void
    {
        $customer = Customer::factory()->create();

        // Eén toevallige klapper telt niet als beste bron.
        Lead::factory()->create(['customer_id' => $customer->id, 'campaign' => 'Geluk', 'status' => LeadStatus::Project, 'value' => 50000]);
        Lead::factory()->count(3)->create(['customer_id' => $customer->id, 'campaign' => 'Mond tot mond', 'status' => LeadStatus::Project, 'value' => 10000]);

        $data = (new MarketingInsights)->build('alles');

        $this->assertSame('Mond tot mond', $data['best_source']['label']);
    }

    public function test_settings_fall_back_to_their_default(): void
    {
        $this->assertSame('55', Setting::get('calculatie.uurtarief'));
        $this->assertSame(21.0, Setting::number('calculatie.btw'));
        $this->assertSame('Mercatorweg 28', Setting::get('bedrijf.adres'));
    }

    public function test_saving_a_setting_sticks_and_unknown_keys_are_ignored(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->patch(route('settings.update'), [
            'settings' => [
                'calculatie.uurtarief' => '62,50',
                'bedrijf.iban' => 'NL12RABO0123456789',
                'iets.verzonnen' => 'wordt genegeerd',
            ],
        ])->assertSessionHas('success');

        $this->assertSame('62,50', Setting::get('calculatie.uurtarief'));
        $this->assertSame(62.5, Setting::number('calculatie.uurtarief'));
        $this->assertSame('NL12RABO0123456789', Setting::get('bedrijf.iban'));
        $this->assertDatabaseMissing('settings', ['key' => 'iets.verzonnen']);
    }

    public function test_the_settings_screen_never_shows_a_secret(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        config(['renovion.lead_intake_token' => 'geheim-token-abc123']);

        $response = $this->actingAs($admin)->get(route('settings.index'));

        $response->assertOk();
        // De staat wel, de waarde nooit.
        $response->assertSee('Token voor de aanvraag-API');
        $response->assertDontSee('geheim-token-abc123');
    }

    public function test_the_portal_preview_shows_what_the_customer_sees(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'name' => 'Badkamer De Groot',
            'progress' => 60,
        ]);

        $project->photos()->create([
            'uploaded_by' => $leider->id,
            'path' => 'project-photos/zichtbaar.jpg',
            'caption' => 'Tegelwerk gereed',
            'client_visible' => true,
        ]);

        $project->photos()->create([
            'uploaded_by' => $leider->id,
            'path' => 'project-photos/intern.jpg',
            'caption' => 'Leiding achter de wand',
            'client_visible' => false,
        ]);

        $this->actingAs($leider)->get(route('portal-preview.show', $project))
            ->assertOk()
            ->assertSee('Zo ziet de klant het')
            ->assertSee('1 zichtbaar, 1 alleen intern')
            // Zonder portaalaccount zegt het scherm dat ook.
            ->assertSee('nog geen portaalaccount');
    }

    public function test_only_management_opens_the_settings(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);

        $this->actingAs($leider)->get(route('settings.index'))->assertForbidden();
        // Rapportages mag de projectleiding wel.
        $this->actingAs($leider)->get(route('reports.index'))->assertOk();
    }

    public function test_the_report_screens_open_for_every_type(): void
    {
        $directie = User::factory()->create(['role' => UserRole::Directie]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);

        foreach (array_keys(ReportBuilder::TYPES) as $type) {
            $this->actingAs($directie)
                ->get(route('reports.index', ['type' => $type, 'project' => $project->id]))
                ->assertOk();
        }

        $this->actingAs($directie)->get(route('marketing.index'))->assertOk()->assertSee('Per bron');
    }
}
