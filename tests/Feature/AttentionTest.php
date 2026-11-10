<?php

namespace Tests\Feature;

use App\Enums\JobReportType;
use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\JobExecution;
use App\Models\JobReport;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AttentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_attention_page_shows_only_exceptions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $overdueProject = Project::factory()->create([
            'name' => 'Badkamer Uitloop',
            'end_date_expected' => today()->subDays(2)->toDateString(),
        ]);
        $quote = Quote::factory()->create([
            'status' => QuoteStatus::Verstuurd,
            'sent_at' => now()->subDays(6),
        ]);
        Task::factory()->create([
            'title' => 'Verlopen taak',
            'deadline' => today()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get('/aandacht');

        $response->assertOk();
        $response->assertSee('Badkamer Uitloop');
        $response->assertSee('Offerte niet opgevolgd');
        $response->assertSee('Verlopen taak');
    }

    public function test_attention_flags_leads_without_planned_follow_up(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $stil = Lead::factory()->create(['next_action_at' => null, 'last_contact_at' => now()->subDays(5)]);
        $stil->customer->update(['name' => 'Familie Stilgevallen']);

        $vers = Lead::factory()->create(['next_action_at' => null, 'last_contact_at' => now()->subDay()]);
        $vers->customer->update(['name' => 'Familie Vers']);

        $response = $this->actingAs($user)->get('/aandacht');

        $response->assertOk();
        $response->assertSee('Aanvraag zonder opvolging');
        $response->assertSee('Familie Stilgevallen');
        $response->assertDontSee('Familie Vers');
    }

    public function test_attention_page_is_empty_when_everything_is_on_track(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->get('/aandacht')
            ->assertOk()
            ->assertSee('Je inbox is leeg')
            ->assertSee('Niets meer te doen');
    }

    public function test_the_inbox_separates_work_to_confirm_from_work_to_act_on(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);

        $entry = ScheduleEntry::factory()->create([
            'user_id' => $uitvoerder->id,
            'project_id' => $project->id,
            'title' => 'Voorzetwanden',
        ]);
        $job = JobExecution::forEntry($entry);

        $job->reports()->create([
            'project_id' => $project->id,
            'reported_by' => $uitvoerder->id,
            'type' => JobReportType::Materiaal,
            'body' => 'Twee gipsplaten beschadigd geleverd.',
            'urgent' => true,
            'status' => JobReport::STATUS_OPEN,
        ]);

        WorkOrder::create([
            'number' => 'WB-2026-0501',
            'project_id' => $project->id,
            'user_id' => $uitvoerder->id,
            'title' => 'Voorzetwanden plaatsen',
            'date' => today(),
            'status' => WorkOrderStatus::Controle,
            'hours' => 8,
            'submitted_at' => now(),
        ]);

        $items = (new AttentionService)->items();

        $melding = $items->firstWhere('label', 'Spoedmelding bouwplaats');
        $this->assertNotNull($melding);
        $this->assertSame('actie', $melding['kind']);
        $this->assertSame('rood', $melding['severity']);

        $werkbon = $items->firstWhere('label', 'Werkbon ter controle');
        $this->assertNotNull($werkbon);
        // Een werkbon wacht op jouw bevestiging, niet op een handeling in het veld.
        $this->assertSame('bevestigen', $werkbon['kind']);

        $this->actingAs($admin)->get('/aandacht')
            ->assertOk()
            ->assertSee('Ter bevestiging')
            ->assertSee('WB-2026-0501: Voorzetwanden plaatsen');
    }

    public function test_uitvoerder_cannot_open_attention_or_automations(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get('/aandacht')->assertForbidden();
        $this->actingAs($uitvoerder)->get('/automations')->assertForbidden();
    }

    public function test_automations_page_lists_automations(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->get('/automations')
            ->assertOk()
            ->assertSee('Offerte-opvolging')
            ->assertSee('Ontbrekend telefoonnummer opvragen');
    }
}
