<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_status_can_be_updated_via_kanban(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $response = $this->actingAs($user)->patchJson(route('leads.status', $lead), [
            'status' => LeadStatus::Contact->value,
            'position' => 2,
        ]);

        $response->assertOk()->assertJsonPath('status', 'contact');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'contact', 'position' => 2]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'lead',
            'auditable_id' => $lead->id,
            'action' => 'status_gewijzigd',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('timeline_events', [
            'customer_id' => $lead->customer_id,
            'type' => 'wijziging',
        ]);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('leads.status', $lead), ['status' => 'bestaat_niet'])
            ->assertUnprocessable();
    }
}
