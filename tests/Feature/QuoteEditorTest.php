<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\QuoteStatus;
use App\Models\Calculation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteEditorTest extends TestCase
{
    use RefreshDatabase;

    private function definitieveCalculation(): Calculation
    {
        $customer = Customer::factory()->create(['name' => 'Familie Jansen']);
        $lead = Lead::factory()->create(['customer_id' => $customer->id, 'service' => 'Badkamerrenovatie']);

        $calculation = Calculation::factory()->definitief()->create([
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'title' => 'Calculatie badkamer Jansen',
            'risk_pct' => 5,
            'margin_pct' => 15,
            'vat_pct' => 21,
        ]);

        $calculation->addLine(['type' => 'arbeid', 'description' => 'Stucwerk', 'quantity' => 10, 'unit_price' => 30]);
        $calculation->addLine(['type' => 'materiaal', 'description' => 'Tegels', 'quantity' => 8, 'unit_price' => 25]);
        $calculation->addLine(['type' => 'stelpost', 'description' => 'Sanitair (stelpost)', 'quantity' => 1, 'unit_price' => 3500]);

        return $calculation->fresh(['lines', 'customer', 'lead']);
    }

    public function test_a_quote_is_created_from_a_definitieve_calculation_with_commercial_lines(): void
    {
        $user = User::factory()->create();
        $calculation = $this->definitieveCalculation();

        $response = $this->actingAs($user)->post('/calculaties/'.$calculation->id.'/offerte');

        $quote = Quote::firstWhere('calculation_id', $calculation->id);
        $response->assertRedirect(route('quotes.show', $quote));

        $this->assertStringStartsWith('REN-'.now()->year.'-', $quote->number);
        $this->assertNotNull($quote->public_token);
        $this->assertNotEmpty($quote->blocks);
        $this->assertSame(LeadStatus::Offerte, $calculation->lead->refresh()->status);

        // Klant ziet commerciële posten: 2 groepen + 1 herkenbare stelpost.
        $quote->load('lines');
        $this->assertSame(3, $quote->lines->count());
        $this->assertTrue($quote->lines->firstWhere('description', 'Sanitair (stelpost)')->is_estimate);

        // Offertetotaal excl. btw = calculatietotaal excl. btw (marge verdeeld, niet zichtbaar).
        $this->assertEqualsWithDelta($calculation->totalExcl(), (float) $quote->subtotal, 0.02);
        $this->assertEqualsWithDelta($calculation->totalIncl(), (float) $quote->total, 0.05);
    }

    public function test_a_concept_calculation_cannot_become_a_quote(): void
    {
        $user = User::factory()->create();
        $calculation = Calculation::factory()->create();

        $this->actingAs($user)->post('/calculaties/'.$calculation->id.'/offerte')
            ->assertRedirect(route('calculations.show', $calculation));

        $this->assertSame(0, Quote::count());
    }

    public function test_blocks_can_be_edited_while_concept(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->create([
            'blocks' => [['key' => 'samenvatting', 'title' => 'Samenvatting', 'body' => 'Oud', 'enabled' => true]],
        ]);

        $this->actingAs($user)->patch('/offertes/'.$quote->id.'/blokken', [
            'blocks' => [
                ['key' => 'samenvatting', 'title' => 'Samenvatting', 'body' => 'Nieuwe tekst voor de klant', 'enabled' => true],
                ['key' => 'faq', 'title' => 'Veelgestelde vragen', 'body' => '', 'enabled' => false],
            ],
        ])->assertRedirect(route('quotes.show', $quote));

        $this->assertSame('Nieuwe tekst voor de klant', $quote->refresh()->blocks[0]['body']);
        $this->assertCount(1, $quote->enabledBlocks());
    }

    public function test_sending_freezes_the_version(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->create();

        $this->actingAs($user)->patch('/offertes/'.$quote->id.'/status', ['status' => 'verstuurd']);

        $quote->refresh();
        $this->assertSame(QuoteStatus::Verstuurd, $quote->status);
        $this->assertDatabaseHas('quote_versions', ['quote_id' => $quote->id, 'version' => 1]);
    }

    public function test_the_public_page_marks_the_quote_as_viewed(): void
    {
        $quote = Quote::factory()->sent()->create();

        $this->get('/offerte/'.$quote->public_token)
            ->assertOk()
            ->assertSee($quote->number)
            ->assertSee('Offerte ondertekenen');

        $quote->refresh();
        $this->assertSame(QuoteStatus::Bekeken, $quote->status);
        $this->assertSame(1, $quote->viewed_count);
        $this->assertDatabaseHas('timeline_events', ['subject_id' => $quote->id, 'subject_type' => 'quote', 'source' => 'website']);
    }

    public function test_a_concept_quote_is_not_publicly_visible(): void
    {
        $quote = Quote::factory()->create();

        $this->get('/offerte/'.$quote->public_token)->assertNotFound();
    }

    public function test_signing_accepts_the_quote_and_creates_a_project(): void
    {
        $quote = Quote::factory()->sent()->create();

        $this->post('/offerte/'.$quote->public_token.'/ondertekenen', [
            'signed_name' => 'R. Jansen',
            'agree' => '1',
        ])->assertRedirect(route('quotes.public', $quote->public_token));

        $quote->refresh();
        $this->assertSame(QuoteStatus::Akkoord, $quote->status);
        $this->assertSame('R. Jansen', $quote->signed_name);
        $this->assertNotNull($quote->signed_at);
        $this->assertNotNull($quote->project_id);

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'quote', 'action' => 'ondertekend', 'source' => 'website']);
        $this->assertDatabaseHas('projects', ['quote_id' => $quote->id]);
    }

    public function test_an_expired_quote_cannot_be_signed(): void
    {
        $quote = Quote::factory()->sent()->create(['valid_until' => now()->subDay()->toDateString()]);

        $this->post('/offerte/'.$quote->public_token.'/ondertekenen', [
            'signed_name' => 'R. Jansen',
            'agree' => '1',
        ]);

        $this->assertTrue($quote->refresh()->status->isOpen());
        $this->assertNull($quote->signed_at);
        $this->assertNull($quote->accepted_at);
    }

    public function test_a_customer_can_request_a_change(): void
    {
        $quote = Quote::factory()->sent()->create();

        $this->post('/offerte/'.$quote->public_token.'/aanpassing', [
            'message' => 'Kan de inloopdouche 1,20 m breed worden?',
        ])->assertRedirect(route('quotes.public', $quote->public_token));

        $quote->refresh();
        $this->assertSame(QuoteStatus::Opvolgen, $quote->status);
        $this->assertSame('Kan de inloopdouche 1,20 m breed worden?', $quote->change_request);
    }

    public function test_a_new_version_reopens_the_quote_with_a_changelog(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->sent()->create();
        $quote->freezeVersion();

        $this->actingAs($user)->post('/offertes/'.$quote->id.'/nieuwe-versie', [
            'note' => 'Inloopdouche verbreed naar 1,20 m',
        ])->assertRedirect(route('quotes.show', $quote));

        $quote->refresh();
        $this->assertSame(2, $quote->version);
        $this->assertSame(QuoteStatus::Concept, $quote->status);
        $this->assertNull($quote->sent_at);
        $this->assertDatabaseHas('quote_versions', ['quote_id' => $quote->id, 'version' => 1]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'quote', 'action' => 'nieuwe_versie']);
    }
}
