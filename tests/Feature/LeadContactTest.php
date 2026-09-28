<?php

namespace Tests\Feature;

use App\Enums\LeadQualification;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_contact_moment_is_logged_on_the_timeline_and_updates_the_lead(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['status' => LeadStatus::Intake, 'next_action' => 'Bellen', 'next_action_at' => now()->subDay()]);

        $response = $this->actingAs($user)->post('/aanvragen/'.$lead->id.'/contactmomenten', [
            'type' => 'telefoon',
            'summary' => 'Gebeld: wil eerst de badkamer, budget rond 15k.',
            'next_action' => 'Offerte-afspraak inplannen',
            'next_action_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('leads.show', $lead));

        $lead->refresh();
        $this->assertNotNull($lead->last_contact_at);
        $this->assertSame('Offerte-afspraak inplannen', $lead->next_action);
        $this->assertSame(LeadStatus::Intake, $lead->status);

        $this->assertDatabaseHas('timeline_events', [
            'customer_id' => $lead->customer_id,
            'type' => 'telefoon',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'lead',
            'auditable_id' => $lead->id,
            'action' => 'contactmoment',
        ]);
    }

    public function test_a_first_contact_moment_moves_a_new_lead_to_contact(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['status' => LeadStatus::Nieuw]);

        $this->actingAs($user)->post('/aanvragen/'.$lead->id.'/contactmomenten', [
            'type' => 'whatsapp',
            'summary' => 'Geappt over de aanvraag.',
        ]);

        $this->assertSame(LeadStatus::Contact, $lead->refresh()->status);
    }

    public function test_a_contact_moment_clears_the_planned_follow_up_when_none_is_given(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['next_action' => 'Bellen', 'next_action_at' => now()->subDay()]);

        $this->actingAs($user)->post('/aanvragen/'.$lead->id.'/contactmomenten', [
            'type' => 'telefoon',
            'summary' => 'Gebeld en afgehandeld.',
        ]);

        $lead->refresh();
        $this->assertNull($lead->next_action);
        $this->assertNull($lead->next_action_at);
    }

    public function test_a_contact_moment_requires_a_summary(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->postJson('/aanvragen/'.$lead->id.'/contactmomenten', ['type' => 'telefoon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['summary']);
    }

    public function test_uitvoerders_cannot_log_contact_moments(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($uitvoerder)
            ->post('/aanvragen/'.$lead->id.'/contactmomenten', ['type' => 'telefoon', 'summary' => 'x'])
            ->assertForbidden();
    }

    public function test_qualification_and_desired_start_can_be_updated(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->patch('/aanvragen/'.$lead->id, [
            'qualification' => 'heet',
            'desired_start' => 'voorjaar 2027',
        ])->assertRedirect(route('leads.show', $lead));

        $lead->refresh();
        $this->assertSame(LeadQualification::Heet, $lead->qualification);
        $this->assertSame('voorjaar 2027', $lead->desired_start);
    }
}
