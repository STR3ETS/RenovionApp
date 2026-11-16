<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Nazorg en service als eigen scherm in de app: alleen de projectleiding, met dezelfde garantieregel. */
class AppServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_report_is_taken_in_and_handled_from_the_app(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'end_date_actual' => today()->subMonths(6), 'warranty_months' => 24]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson(route('api.app.service.store'), [
            'project_id' => $project->id,
            'title' => 'Kit laat los bij de douchehoek',
            'description' => 'Klant meldt dat de kit loslaat.',
            'priority' => 'spoed',
            'reported_via' => 'telefoon',
        ])->assertOk()->assertJsonPath('message', fn (string $message) => str_contains($message, 'Binnen de garantietermijn'));

        $ticket = ServiceTicket::first();
        $this->withToken($token)->getJson(route('api.app.service.index'))->assertOk()
            ->assertJsonPath('kpis.open', 1)
            ->assertJsonPath('kpis.urgent', 1)
            ->assertJsonPath('tickets.0.id', $ticket->id)
            ->assertJsonPath('tickets.0.urgent', true)
            ->assertJsonPath('tickets.0.warranty', true)
            ->assertJsonPath('tickets.0.status.value', 'nieuw')
            ->assertJsonPath('filters.1.value', 'nieuw');

        $this->withToken($token)->patchJson(route('api.app.service.update', $ticket), [
            'status' => 'afgehandeld',
            'assigned_to' => $admin->id,
            'planned_on' => null,
            'resolution' => 'Opnieuw gekit.',
            'warranty' => false,
        ])->assertOk()->assertJsonPath('message', 'Melding bijgewerkt.');

        $ticket->refresh();
        $this->assertSame('afgehandeld', $ticket->status);
        $this->assertFalse($ticket->warranty);
        $this->assertNotNull($ticket->resolved_at);

        $this->withToken($token)->getJson(route('api.app.service.index', ['status' => 'afgehandeld']))->assertOk()
            ->assertJsonPath('filter', 'afgehandeld')
            ->assertJsonPath('kpis.resolved', 1)
            ->assertJsonPath('tickets.0.open', false);
    }

    public function test_executors_cannot_open_aftercare(): void
    {
        $token = User::factory()->uitvoerder()->create()->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.service.index'))->assertForbidden();
        $this->withToken($token)->postJson(route('api.app.service.store'), [])->assertForbidden();
    }
}
