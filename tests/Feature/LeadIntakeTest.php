<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadIntakeTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-intake-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['renovion.lead_intake_token' => $this->token]);
    }

    public function test_intake_is_rejected_without_valid_token(): void
    {
        $this->postJson('/api/aanvragen', ['name' => 'Familie Test'])
            ->assertUnauthorized();

        $this->postJson('/api/aanvragen', ['name' => 'Familie Test'], ['X-Intake-Token' => 'fout-token'])
            ->assertUnauthorized();
    }

    public function test_website_request_creates_customer_lead_and_timeline_event(): void
    {
        $response = $this->postJson('/api/aanvragen', [
            'name' => 'Familie Pietersen',
            'email' => 'pietersen@example.nl',
            'phone' => '06-12345678',
            'city' => 'Arnhem',
            'service' => 'Badkamerrenovatie',
            'message' => 'Graag een offerte voor onze badkamer.',
        ], ['X-Intake-Token' => $this->token]);

        $response->assertCreated()->assertJsonPath('status', LeadStatus::Nieuw->value);

        $this->assertDatabaseHas('customers', ['email' => 'pietersen@example.nl', 'city' => 'Arnhem']);
        $this->assertDatabaseHas('leads', ['service' => 'Badkamerrenovatie', 'status' => 'nieuw', 'source' => 'website']);
        $this->assertDatabaseHas('timeline_events', ['type' => 'aanvraag', 'source' => 'website']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'aangemaakt', 'source' => 'website']);
    }

    public function test_existing_customer_is_reused_based_on_email(): void
    {
        $customer = Customer::factory()->create(['email' => 'bekend@example.nl']);

        $this->postJson('/api/aanvragen', [
            'name' => 'Bekende Klant',
            'email' => 'bekend@example.nl',
            'service' => 'Stucwerk',
        ], ['X-Intake-Token' => $this->token])->assertCreated();

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('leads', ['customer_id' => $customer->id, 'service' => 'Stucwerk']);
    }
}
