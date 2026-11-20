<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\ScheduleEntryType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\JobExecution;
use App\Models\Photo;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\RfqBid;
use App\Models\ScheduleEntry;
use App\Models\Subcontractor;
use App\Models\User;
use App\Services\CostReviewService;
use App\Support\CertificateCatalogue;
use App\Support\JobChecklistTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prijsuitvraag met marge: beste bod, de bodem van twaalf procent, de
 * automatische inkooporder en de fotoplicht.
 */
class BidMarginTest extends TestCase
{
    use RefreshDatabase;

    private function partner(string $name, array $attributes = []): Subcontractor
    {
        $partner = Subcontractor::create(['name' => $name, 'id_checked' => true, 'phone' => '06 1234 5678', ...$attributes]);

        foreach (array_keys(CertificateCatalogue::PARTNER_DOCUMENTS) as $type) {
            $partner->documents()->create(['type' => $type, 'valid_until' => today()->addYear()]);
        }

        foreach (array_keys(CertificateCatalogue::PARTNER_AGREEMENTS) as $type) {
            $partner->documents()->create(['type' => $type, 'signed_at' => now()]);
        }

        return $partner->fresh(['documents']);
    }

    private function rfq(?Project $project = null): Rfq
    {
        return Rfq::create([
            'number' => Rfq::nextNumber(),
            'project_id' => ($project ?? Project::factory()->create(['customer_id' => Customer::factory()]))->id,
            'title' => 'Stucwerk begane grond',
            'chapter_code' => '42',
            'budget' => 4000,
            'status' => Rfq::STATUS_SENT,
        ]);
    }

    /** Verkoop is steeds 100 m2 maal 50, dus 5.000: de inkoop bepaalt de marge. */
    private function bid(Rfq $rfq, Subcontractor $partner, float $price, array $extra = []): RfqBid
    {
        return $rfq->bids()->create([
            'subcontractor_id' => $partner->id, 'price' => $price, 'completeness' => 100,
            'sale_quantity' => 100, 'sale_unit' => 'm2', 'sale_price' => 50,
            ...$extra,
        ]);
    }

    public function test_the_margin_is_what_remains_of_the_sale(): void
    {
        $rfq = $this->rfq();
        $bid = $this->bid($rfq, $this->partner('Stuc Oost'), 4000);

        $this->assertSame(5000.0, $bid->saleTotal());
        $this->assertSame(20.0, $bid->marginPct());
        $this->assertFalse($bid->needsDirectorApproval());

        // Zonder verkoop is er geen marge te berekenen.
        $zonder = $rfq->bids()->create(['subcontractor_id' => $this->partner('Zonder verkoop')->id, 'price' => 3000]);
        $this->assertNull($zonder->marginPct());

        // Zonder hoeveelheid is de prijs het hele bedrag.
        $zonder->update(['sale_price' => 4000]);
        $this->assertSame(25.0, $zonder->fresh()->marginPct());
    }

    public function test_the_best_bid_has_the_highest_margin_with_complete_scope_and_an_approved_partner(): void
    {
        $rfq = $this->rfq();

        // Het goedkoopst, maar prijst niet alles.
        $this->bid($rfq, $this->partner('Incompleet'), 3000, ['completeness' => 80]);
        // Goedkoop en compleet, maar het dossier is niet rond.
        $this->bid($rfq, Subcontractor::create(['name' => 'Zonder papieren']), 3200);
        $goed = $this->bid($rfq, $this->partner('Compleet en goedgekeurd'), 3900);
        $this->bid($rfq, $this->partner('Duurder'), 4300);

        $this->assertTrue($rfq->fresh()->bestComparableBid()->is($goed));

        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $this->actingAs($inkoper)->get(route('rfqs.show', $rfq))
            ->assertOk()
            ->assertSee('Beste bod')
            // Alle biedingen blijven zichtbaar.
            ->assertSee('Incompleet')
            ->assertSee('Duurder')
            ->assertSee('22%');
    }

    public function test_awarding_creates_a_purchase_order_with_photo_duty_and_logs_every_partner(): void
    {
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $rfq = $this->rfq();
        $winnaar = $this->partner('Stuc Oost');
        $verliezer = $this->partner('Stuc West');
        $rfq->invited()->attach([$winnaar->id => ['invited_at' => now()], $verliezer->id => ['invited_at' => now()]]);

        $bid = $this->bid($rfq, $winnaar, 4000);
        $this->bid($rfq, $verliezer, 4200);

        $this->actingAs($inkoper)->post(route('rfqs.award', [$rfq, $bid]))->assertSessionHas('success');

        $order = PurchaseOrder::firstWhere('rfq_id', $rfq->id);

        $this->assertNotNull($order);
        $this->assertTrue($order->photo_required);
        $this->assertSame($winnaar->id, $order->subcontractor_id);
        $this->assertSame('onderaanneming', $order->category);
        $this->assertSame(PurchaseOrder::STATUS_SENT, $order->status);
        $this->assertSame(4000.0, $order->total());
        $this->assertSame($order->id, $bid->fresh()->purchase_order_id);

        // Gekozen en niet gekozen staan allebei in het logboek van de partij.
        $this->assertDatabaseHas('subcontractor_logs', ['subcontractor_id' => $winnaar->id, 'project_id' => $rfq->project_id]);
        $this->assertStringStartsWith('Gegund:', $winnaar->logs()->first()->body);
        $this->assertStringStartsWith('Niet gegund:', $verliezer->logs()->first()->body);

        // De overeenkomst voor dit project staat klaar om vastgelegd te worden.
        $this->assertDatabaseHas('project_agreements', [
            'project_id' => $rfq->project_id, 'subcontractor_id' => $winnaar->id, 'rfq_id' => $rfq->id, 'recorded_at' => null,
        ]);
    }

    public function test_a_bid_under_the_floor_needs_a_reason_and_the_directors(): void
    {
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $directie = User::factory()->create(['role' => UserRole::Directie, 'name' => 'Imad Directie']);
        $rfq = $this->rfq();
        // 5.000 verkoop, 4.500 inkoop: tien procent, onder de bodem van twaalf.
        $bid = $this->bid($rfq, $this->partner('Krap bod'), 4500);

        $this->assertTrue($bid->needsDirectorApproval());

        // Een inkoper mag dit niet, ook niet met een reden.
        $this->actingAs($inkoper)->post(route('rfqs.award', [$rfq, $bid]), ['margin_reason' => 'Vaste partner'])
            ->assertSessionHas('error');
        // De directie niet zonder reden.
        $this->actingAs($directie)->post(route('rfqs.award', [$rfq, $bid]))->assertSessionHas('error');
        $this->assertSame(Rfq::STATUS_SENT, $rfq->fresh()->status);
        $this->assertDatabaseCount('purchase_orders', 0);

        // Met reden én directie kan het, en dat ligt vast bij het bod.
        $this->actingAs($directie)->post(route('rfqs.award', [$rfq, $bid]), ['margin_reason' => 'Strategische klant, vervolgwerk toegezegd'])
            ->assertSessionHas('success');

        $approval = $bid->fresh()->margin_approval;
        $this->assertSame('Imad Directie', $approval['door']);
        $this->assertSame('Strategische klant, vervolgwerk toegezegd', $approval['reden']);
        $this->assertSame(10.0, (float) $approval['pct']);
        $this->assertSame(Rfq::STATUS_AWARDED, $rfq->fresh()->status);
    }

    public function test_a_bid_between_floor_and_baseline_is_awarded_with_a_warning(): void
    {
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $rfq = $this->rfq();
        // Vijftien procent: boven de bodem, onder de zeventien waar we op sturen.
        $bid = $this->bid($rfq, $this->partner('Net genoeg'), 4250);

        $response = $this->actingAs($inkoper)->post(route('rfqs.award', [$rfq, $bid]));

        $response->assertSessionHas('success');
        $this->assertStringContainsString('onder de 17%', session('success'));
    }

    public function test_the_awarded_order_counts_as_a_commitment_in_the_cost_review(): void
    {
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $rfq = $this->rfq();
        $bid = $this->bid($rfq, $this->partner('Stuc Oost'), 4000);

        $this->actingAs($inkoper)->post(route('rfqs.award', [$rfq, $bid]));

        $open = PurchaseOrder::uninvoiced()->where('project_id', $rfq->project_id)->with('lines')->get();
        $this->assertSame(4000.0, $open->sum(fn (PurchaseOrder $order) => $order->total()));
        $this->assertInstanceOf(CostReviewService::class, app(CostReviewService::class));
    }

    public function test_a_subcontractor_invoice_is_not_approved_without_photos_before_during_and_after(): void
    {
        $administratie = User::factory()->create(['role' => UserRole::Administratie]);
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $rfq = $this->rfq($project);
        $bid = $this->bid($rfq, $this->partner('Stuc Oost'), 4000);
        $this->actingAs($inkoper)->post(route('rfqs.award', [$rfq, $bid]));

        $order = PurchaseOrder::firstWhere('rfq_id', $rfq->id);
        $invoice = PurchaseInvoice::create([
            'number' => 'F-2026-77', 'supplier' => 'Stuc Oost', 'project_id' => $project->id, 'purchase_order_id' => $order->id,
            'category' => 'onderaanneming', 'date' => today(), 'due_date' => today()->addDays(30), 'amount' => 4000, 'vat_pct' => 21,
            'status' => PurchaseInvoice::STATUS_NEW,
        ]);

        $this->assertSame(JobChecklistTemplates::MOMENTS, $order->missingPhotoMoments());

        $this->actingAs($administratie)->patch(route('purchase-invoices.update', $invoice), ['status' => 'goedgekeurd'])
            ->assertSessionHas('error');
        $this->assertSame(PurchaseInvoice::STATUS_NEW, $invoice->fresh()->status);

        // De klus op dit project, met een foto bij een openings-, een vak- en een afsluitstap.
        $entry = ScheduleEntry::factory()->create([
            'user_id' => User::factory()->uitvoerder()->create()->id, 'project_id' => $project->id,
            'type' => ScheduleEntryType::Project, 'title' => 'Stucwerk', 'date' => today(),
        ]);
        $job = JobExecution::forEntry($entry);
        $steps = $job->steps()->orderBy('position')->get();

        $foto = fn ($step) => Photo::factory()->create([
            'project_id' => $project->id, 'job_step_id' => $step->id, 'uploaded_by' => $entry->user_id,
        ]);

        $foto($steps->first(fn ($step) => JobChecklistTemplates::momentFor($step->title) === 'voor'));
        $foto($steps->first(fn ($step) => JobChecklistTemplates::momentFor($step->title) === 'tijdens'));

        // Voor en tijdens zijn er, na ontbreekt nog.
        $this->assertSame(['na'], $order->fresh()->missingPhotoMoments());
        $this->actingAs($administratie)->patch(route('purchase-invoices.update', $invoice), ['status' => 'goedgekeurd'])
            ->assertSessionHas('error');

        $foto($steps->first(fn ($step) => JobChecklistTemplates::momentFor($step->title) === 'na'));

        $this->actingAs($administratie)->patch(route('purchase-invoices.update', $invoice), ['status' => 'goedgekeurd'])
            ->assertSessionHas('success');
        $this->assertSame(PurchaseInvoice::STATUS_APPROVED, $invoice->fresh()->status);
    }

    public function test_an_ordinary_purchase_invoice_has_no_photo_duty(): void
    {
        $administratie = User::factory()->create(['role' => UserRole::Administratie]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);

        $invoice = PurchaseInvoice::create([
            'number' => 'F-2026-78', 'supplier' => 'Bouwmaat', 'project_id' => $project->id,
            'category' => 'materiaal', 'date' => today(), 'due_date' => today()->addDays(30), 'amount' => 250, 'vat_pct' => 21,
            'status' => PurchaseInvoice::STATUS_NEW,
        ]);

        $this->actingAs($administratie)->patch(route('purchase-invoices.update', $invoice), ['status' => 'goedgekeurd'])
            ->assertSessionHas('success');
    }

    public function test_a_bid_is_entered_with_generous_number_input_and_a_whatsapp_flag(): void
    {
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $rfq = $this->rfq();
        $partner = $this->partner('Stuc Oost');
        $rfq->invited()->attach($partner->id, ['invited_at' => now()]);

        $this->actingAs($inkoper)->post(route('rfq-bids.store', $rfq), [
            'subcontractor_id' => $partner->id, 'price' => 'EUR 4.250,50',
            'sale_quantity' => '120', 'sale_unit' => 'm2', 'sale_price' => '45,00', 'via_whatsapp' => 1,
        ])->assertSessionHas('success');

        $bid = $rfq->bids()->first();
        $this->assertSame(4250.50, (float) $bid->price);
        $this->assertSame(5400.0, $bid->saleTotal());
        $this->assertTrue($bid->via_whatsapp);

        // De uitvraag via WhatsApp staat klaar met het nummer in het goede formaat.
        $this->assertSame('31612345678', $partner->whatsappNumber());
        $this->actingAs($inkoper)->get(route('rfqs.show', $rfq))
            ->assertOk()
            ->assertSee('https://wa.me/31612345678?text=', false)
            ->assertSee('via WhatsApp');
    }

    public function test_the_sale_amount_of_a_bid_can_be_taken_into_our_draft_quote(): void
    {
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $rfq = $this->rfq($project);
        $bid = $this->bid($rfq, $this->partner('Stuc Oost'), 4000);

        // Zonder conceptofferte valt er niets over te nemen.
        $this->actingAs($inkoper)->post(route('rfq-bids.adopt', [$rfq, $bid]))->assertSessionHas('error');

        $quote = Quote::factory()->create([
            'customer_id' => $project->customer_id, 'project_id' => $project->id, 'status' => QuoteStatus::Concept,
        ]);

        $this->actingAs($inkoper)->post(route('rfq-bids.adopt', [$rfq, $bid]))->assertSessionHas('success');

        $this->assertDatabaseHas('quote_lines', [
            'quote_id' => $quote->id, 'description' => 'Stucwerk begane grond', 'quantity' => 100, 'unit' => 'm2', 'unit_price' => 50, 'total' => 5000,
        ]);
        $this->assertSame(5000.0, (float) $quote->fresh()->subtotal);
    }
}
