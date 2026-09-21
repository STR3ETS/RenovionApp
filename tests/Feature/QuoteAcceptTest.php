<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\QuoteStatus;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteAcceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_a_quote_creates_a_project_and_updates_the_lead(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->status(LeadStatus::Offerte)->create(['service' => 'Badkamerrenovatie']);
        $quote = Quote::factory()->sent()->create([
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
        ]);
        QuoteLine::factory()->create(['quote_id' => $quote->id, 'quantity' => 10, 'unit_price' => 100, 'vat_rate' => 21, 'total' => 1000]);
        $quote->recalculateTotals();

        $response = $this->actingAs($user)->patch(route('quotes.status', $quote), [
            'status' => QuoteStatus::Akkoord->value,
        ]);

        $quote->refresh();
        $lead->refresh();

        $this->assertSame(QuoteStatus::Akkoord, $quote->status);
        $this->assertNotNull($quote->accepted_at);
        $this->assertSame(LeadStatus::Project, $lead->status);

        $this->assertDatabaseHas('projects', [
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'quote_id' => $quote->id,
            'status' => 'voorbereiding',
            'value' => '1210.00',
            'deposit_amount' => '363.00',
        ]);

        $this->assertNotNull($quote->project_id);
        $response->assertRedirect(route('projects.show', $quote->project_id));

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'quote', 'auditable_id' => $quote->id, 'action' => 'akkoord']);
        $this->assertDatabaseHas('timeline_events', ['customer_id' => $lead->customer_id, 'type' => 'projectupdate']);
    }

    public function test_quote_totals_are_calculated_from_lines(): void
    {
        $quote = Quote::factory()->create();
        QuoteLine::factory()->create(['quote_id' => $quote->id, 'quantity' => 10, 'unit_price' => 50, 'vat_rate' => 21, 'total' => 500]);
        QuoteLine::factory()->create(['quote_id' => $quote->id, 'quantity' => 2, 'unit_price' => 100, 'vat_rate' => 9, 'total' => 200]);

        $quote->recalculateTotals();

        $this->assertSame('700.00', $quote->subtotal);
        $this->assertSame('123.00', $quote->vat_amount);
        $this->assertSame('823.00', $quote->total);
    }

    public function test_closed_quote_cannot_change_status(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->create(['status' => QuoteStatus::Afgewezen]);

        $this->actingAs($user)
            ->from(route('quotes.show', $quote))
            ->patch(route('quotes.status', $quote), ['status' => QuoteStatus::Verstuurd->value])
            ->assertSessionHas('error');

        $this->assertSame(QuoteStatus::Afgewezen, $quote->refresh()->status);
    }
}
