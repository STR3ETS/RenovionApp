<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\AutomationSetting;
use App\Models\ChatChannel;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AutomationModeTest extends TestCase
{
    use RefreshDatabase;

    private function verstuurdeOfferte(): Quote
    {
        return Quote::factory()->create([
            'status' => QuoteStatus::Verstuurd,
            'sent_at' => now()->subDays(4),
        ]);
    }

    public function test_admins_can_change_the_mode_per_rule(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($sales)->patch('/automations/offerte-opvolging', ['mode' => 'uit'])->assertForbidden();

        $this->actingAs($admin)->patch('/automations/offerte-opvolging', ['mode' => 'signaleren'])
            ->assertRedirect();

        $this->assertDatabaseHas('automation_settings', ['key' => 'offerte-opvolging', 'mode' => 'signaleren']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'automation_setting', 'action' => 'modus_gewijzigd']);

        $this->actingAs($admin)->patch('/automations/bestaat-niet', ['mode' => 'uit'])->assertNotFound();
    }

    public function test_mode_uit_skips_the_rule_entirely(): void
    {
        $quote = $this->verstuurdeOfferte();
        AutomationSetting::create(['key' => 'offerte-opvolging', 'mode' => 'uit']);

        Artisan::call('automations:run');

        $this->assertSame(QuoteStatus::Verstuurd, $quote->refresh()->status);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseMissing('automation_runs', ['automation' => 'offerte-opvolging']);
    }

    public function test_mode_signaleren_only_posts_a_chat_signal(): void
    {
        $quote = $this->verstuurdeOfferte();
        AutomationSetting::create(['key' => 'offerte-opvolging', 'mode' => 'signaleren']);

        Artisan::call('automations:run');

        // Geen actie: status en taken onaangeroerd.
        $this->assertSame(QuoteStatus::Verstuurd, $quote->refresh()->status);
        $this->assertDatabaseCount('tasks', 0);

        $kanaal = ChatChannel::firstWhere('name', 'Algemeen');
        $bericht = $kanaal->messages()->whereNull('user_id')->first();
        $this->assertStringContainsString('staat 4 dagen open', $bericht->body);
        $this->assertNull($bericht->nova['action'] ?? null);
    }

    public function test_mode_bevestigen_posts_a_confirmable_nova_proposal(): void
    {
        $quote = $this->verstuurdeOfferte();
        AutomationSetting::create(['key' => 'offerte-opvolging', 'mode' => 'bevestigen']);

        Artisan::call('automations:run');

        $kanaal = ChatChannel::firstWhere('name', 'Algemeen');
        $bericht = $kanaal->messages()->whereNull('user_id')->first();
        $this->assertSame('create_task', $bericht->nova['action']['type']);
        $this->assertFalse($bericht->nova['executed']);
        $this->assertDatabaseCount('tasks', 0);

        // "Ja, doe maar" voert de taak uit via de bestaande beveiligde flow.
        $admin = User::factory()->create();
        $this->actingAs($admin)->postJson('/chat/berichten/'.$bericht->id.'/nova-bevestigen')->assertOk();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Offerte '.$quote->number.' opvolgen (4 dagen open)',
            'customer_id' => $quote->customer_id,
        ]);
    }

    public function test_deadline_reminder_creates_a_task_for_the_responsible(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $package = WorkPackage::factory()->create([
            'deadline' => today()->addDay()->toDateString(),
            'responsible_id' => $uitvoerder->id,
        ]);

        Artisan::call('automations:run');

        $this->assertDatabaseHas('tasks', [
            'project_id' => $package->project_id,
            'owner_id' => $uitvoerder->id,
            'source' => 'automation',
        ]);
    }

    public function test_waiting_on_client_reminds_after_three_days(): void
    {
        $project = Project::factory()->create();
        $phase = $project->phases()->firstWhere('position', 0);
        $phase->update(['status' => PhaseStatus::WachtOpKlant]);
        $phase->newQuery()->whereKey($phase->id)->update(['updated_at' => now()->subDays(4)]);

        Artisan::call('automations:run');

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'source' => 'automation',
        ]);
    }

    public function test_delivery_phase_done_generates_a_concept_report(): void
    {
        $project = Project::factory()->create();
        $project->phases()->firstWhere('name', 'Oplevering')->update(['status' => PhaseStatus::Gereed, 'completed_at' => now()]);

        Artisan::call('automations:run');

        $this->assertNotNull($project->refresh()->deliveryReport);
        $this->assertNull($project->deliveryReport->generated_by);

        // Idempotent: nog een run maakt geen tweede rapport.
        Artisan::call('automations:run');
        $this->assertDatabaseCount('delivery_reports', 1);
    }
}
