<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadKanbanMobileTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_status_strip_opens_on_the_first_status_with_leads(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Lead::factory()->status(LeadStatus::Contact)->count(2)->create();
        Lead::factory()->status(LeadStatus::Intake)->create();

        $response = $this->actingAs($admin)->get(route('leads.index', ['view' => 'kanban']));

        $response->assertOk()
            // Statusbalk met per status het aantal; "Nieuw" is leeg, dus mobiel start op "Contact".
            ->assertSee('x-data="{ status: \'contact\' }"', false)
            ->assertSeeInOrder(['Nieuw', '0', 'Contact', '2', 'Intake', '1'])
            ->assertSee('Geen aanvragen met status nieuw');
    }
}
