<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Services\NovaAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NovaTest extends TestCase
{
    use RefreshDatabase;

    public function test_nova_requires_authentication(): void
    {
        $this->postJson('/nova', ['message' => 'test'])->assertUnauthorized();
    }

    public function test_propose_explains_when_api_key_is_missing(): void
    {
        config(['renovion.ai.api_key' => null]);

        $this->actingAs(User::factory()->create())
            ->postJson('/nova', ['message' => 'Maak een taak aan'])
            ->assertOk()
            ->assertJsonPath('type', 'answer')
            ->assertJsonPath('text', fn (string $text) => str_contains($text, 'niet geconfigureerd'));
    }

    public function test_propose_returns_the_assistant_result(): void
    {
        config(['renovion.ai.api_key' => 'test-key']);

        $this->mock(NovaAssistant::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('propose')->once()->andReturn([
                'type' => 'proposal',
                'action' => ['type' => 'create_task', 'params' => ['title' => 'Testtaak']],
                'preview' => 'Taak: "Testtaak"',
            ]);
        });

        $this->actingAs(User::factory()->create())
            ->postJson('/nova', ['message' => 'Zet een taak om te testen'])
            ->assertOk()
            ->assertJsonPath('type', 'proposal')
            ->assertJsonPath('action.type', 'create_task');
    }

    public function test_execute_creates_a_task_with_nova_as_source(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($user)->postJson('/nova/uitvoeren', [
            'action' => [
                'type' => 'create_task',
                'params' => [
                    'title' => 'Aanbetaling Bakker controleren',
                    'customer_id' => $customer->id,
                    'deadline' => today()->addDays(2)->toDateString(),
                    'priority' => 'hoog',
                ],
            ],
        ]);

        $response->assertOk()->assertJsonStructure(['message', 'url']);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Aanbetaling Bakker controleren',
            'customer_id' => $customer->id,
            'owner_id' => $user->id,
            'source' => 'nova',
            'priority' => 'hoog',
        ]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'task', 'action' => 'aangemaakt', 'source' => 'nova']);
        $this->assertDatabaseHas('timeline_events', ['customer_id' => $customer->id, 'source' => 'nova']);
    }

    public function test_execute_creates_an_appointment_with_voice_as_source(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($user)->postJson('/nova/uitvoeren', [
            'action' => [
                'type' => 'create_appointment',
                'params' => [
                    'user_id' => $user->id,
                    'date' => today()->addDay()->toDateString(),
                    'start_time' => '10:00',
                    'type' => 'terugbel',
                    'customer_id' => $customer->id,
                ],
            ],
            'via_voice' => true,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('schedule_entries', [
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'type' => 'terugbel',
        ]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'schedule_entry', 'source' => 'voice']);
    }

    public function test_execute_creates_a_note_on_the_customer_timeline(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)->postJson('/nova/uitvoeren', [
            'action' => [
                'type' => 'create_note',
                'params' => ['customer_id' => $customer->id, 'body' => 'Klant belde over de planning.'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('timeline_events', [
            'customer_id' => $customer->id,
            'type' => 'notitie',
            'body' => 'Klant belde over de planning.',
            'source' => 'nova',
        ]);
    }

    public function test_execute_derives_customer_from_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($user)->postJson('/nova/uitvoeren', [
            'action' => [
                'type' => 'create_task',
                'params' => ['title' => 'Materiaal nabellen', 'project_id' => $project->id],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Materiaal nabellen',
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
        ]);
    }

    public function test_execute_rejects_unknown_actions(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/nova/uitvoeren', [
            'action' => ['type' => 'delete_everything', 'params' => []],
        ])->assertUnprocessable();
    }

    public function test_execute_validates_action_params(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/nova/uitvoeren', [
            'action' => ['type' => 'create_task', 'params' => ['customer_id' => 999999]],
        ])->assertUnprocessable();
    }
}
