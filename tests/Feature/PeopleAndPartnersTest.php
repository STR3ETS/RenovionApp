<?php

namespace Tests\Feature;

use App\Actions\AwardRfq;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Rfq;
use App\Models\ScheduleEntry;
use App\Models\Subcontractor;
use App\Models\User;
use App\Support\CertificateCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeopleAndPartnersTest extends TestCase
{
    use RefreshDatabase;

    /** Een partner met een volledig dossier, dus klaar om werk te krijgen. */
    private function approvedPartner(array $attributes = []): Subcontractor
    {
        $partner = Subcontractor::create([
            'name' => 'Tegelzetters Arnhem',
            'contact_name' => 'Rob Jansen',
            'email' => 'rob@tegelzetters.test',
            'trades' => ['tegelwerk'],
            'id_checked' => true,
            ...$attributes,
        ]);

        foreach (array_keys(CertificateCatalogue::PARTNER_DOCUMENTS) as $type) {
            $partner->documents()->create(['type' => $type, 'valid_until' => today()->addYear()]);
        }

        foreach (array_keys(CertificateCatalogue::PARTNER_AGREEMENTS) as $type) {
            $partner->documents()->create(['type' => $type, 'signed_at' => now()]);
        }

        return $partner->fresh(['documents']);
    }

    public function test_a_partner_dossier_lists_every_requirement(): void
    {
        $partner = Subcontractor::create(['name' => 'Nieuwe partner']);
        $dossier = $partner->dossier();

        // Legitimatie, vier documenten en vier overeenkomsten: negen punten.
        $this->assertCount(9, $dossier['items']);
        $this->assertCount(9, $dossier['open']);
        $this->assertFalse($dossier['complete']);
        $this->assertSame(Subcontractor::STATUS_INCOMPLETE, $partner->status());
        $this->assertFalse($partner->mayBeInvited());
    }

    public function test_a_complete_dossier_makes_a_partner_approved(): void
    {
        $partner = $this->approvedPartner();

        $this->assertTrue($partner->dossier()['complete']);
        $this->assertSame(Subcontractor::STATUS_APPROVED, $partner->status());
        $this->assertTrue($partner->mayBeInvited());
    }

    public function test_an_expired_document_pulls_a_partner_out_of_approved(): void
    {
        $partner = $this->approvedPartner();
        $partner->documents()->where('type', 'VCA-certificaat')->update(['valid_until' => today()->subDay()]);

        $partner = $partner->fresh(['documents']);

        $this->assertSame(Subcontractor::STATUS_EXPIRED, $partner->status());
        $this->assertFalse($partner->mayBeInvited());
    }

    public function test_a_document_expiring_within_thirty_days_is_flagged_but_still_valid(): void
    {
        $partner = $this->approvedPartner();
        $partner->documents()->where('type', 'KvK-uittreksel')->update(['valid_until' => today()->addDays(20)]);

        $dossier = $partner->fresh(['documents'])->dossier();
        $item = collect($dossier['items'])->firstWhere('key', 'KvK-uittreksel');

        $this->assertSame('binnenkort', $item['state']);
        // Binnenkort verlopen is nog geen blokkade, maar wel een signaal.
        $this->assertFalse($dossier['complete']);
    }

    public function test_blocking_a_partner_overrides_a_complete_dossier(): void
    {
        $partner = $this->approvedPartner();
        $partner->update(['blocked' => true, 'blocked_reason' => 'Werk niet volgens afspraak opgeleverd']);

        $this->assertSame(Subcontractor::STATUS_BLOCKED, $partner->fresh()->status());
        $this->assertFalse($partner->fresh()->mayBeInvited());
    }

    public function test_a_partner_without_a_complete_dossier_is_not_invited(): void
    {
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $goed = $this->approvedPartner();
        $slecht = Subcontractor::create(['name' => 'Papieren niet in orde']);

        $rfq = Rfq::create([
            'number' => Rfq::nextNumber(),
            'project_id' => $project->id,
            'title' => 'Tegelwerk badkamer',
            'budget' => 4000,
            'status' => Rfq::STATUS_DRAFT,
        ]);

        $this->actingAs($inkoper)->post(route('rfqs.invite', $rfq), [
            'subcontractor_ids' => [$goed->id, $slecht->id],
        ])->assertSessionHas('error');

        $rfq->refresh();

        $this->assertTrue($rfq->invited->contains($goed));
        $this->assertFalse($rfq->invited->contains($slecht));
        // Er is wel iemand uitgenodigd, dus de aanvraag staat uitgezet.
        $this->assertSame(Rfq::STATUS_SENT, $rfq->status);
    }

    public function test_awarding_turns_a_bid_into_a_commitment(): void
    {
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $partner = $this->approvedPartner();

        $rfq = Rfq::create([
            'number' => Rfq::nextNumber(),
            'project_id' => $project->id,
            'title' => 'Tegelwerk badkamer',
            'budget' => 4000,
            'status' => Rfq::STATUS_SENT,
        ]);

        $bid = $rfq->bids()->create([
            'subcontractor_id' => $partner->id,
            'price' => 3600,
            'can_start_on' => today()->addWeek(),
            'days' => 4,
            'completeness' => 100,
        ]);

        $message = (new AwardRfq)->handle($rfq->fresh(), $bid, $inkoper);

        $rfq->refresh();

        $this->assertSame(Rfq::STATUS_AWARDED, $rfq->status);
        $this->assertSame($partner->id, $rfq->awarded_to);
        $this->assertNotNull($rfq->order_number);
        $this->assertSame(400.0, $rfq->savings());
        $this->assertStringContainsString('onder', $message);
        // Het aantal opdrachten van de partner loopt op.
        $this->assertSame(1, $partner->fresh()->assignments);
        // En er staat een taak om de opdrachtbevestiging getekend terug te krijgen.
        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'priority' => 'hoog']);
    }

    public function test_awarding_to_a_partner_with_an_open_dossier_is_refused(): void
    {
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $partner = Subcontractor::create(['name' => 'Papieren niet in orde']);

        $rfq = Rfq::create([
            'number' => Rfq::nextNumber(),
            'project_id' => $project->id,
            'title' => 'Tegelwerk',
            'budget' => 4000,
            'status' => Rfq::STATUS_SENT,
        ]);

        $bid = $rfq->bids()->create(['subcontractor_id' => $partner->id, 'price' => 3000]);

        $this->actingAs($inkoper)
            ->post(route('rfqs.award', [$rfq, $bid]))
            ->assertSessionHas('error');

        $this->assertSame(Rfq::STATUS_SENT, $rfq->fresh()->status);
    }

    public function test_only_a_complete_bid_counts_as_comparable(): void
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $goedkoop = $this->approvedPartner(['name' => 'Goedkoop maar incompleet']);
        $compleet = $this->approvedPartner(['name' => 'Compleet']);

        $rfq = Rfq::create([
            'number' => Rfq::nextNumber(),
            'project_id' => $project->id,
            'title' => 'Tegelwerk',
            'budget' => 4000,
            'status' => Rfq::STATUS_SENT,
        ]);

        $rfq->bids()->create(['subcontractor_id' => $goedkoop->id, 'price' => 2900, 'completeness' => 80]);
        $rfq->bids()->create(['subcontractor_id' => $compleet->id, 'price' => 3400, 'completeness' => 100]);

        $best = $rfq->fresh(['bids.subcontractor'])->bestComparableBid();

        // De goedkoopste is niet de beste: die prijst niet alles.
        $this->assertSame($compleet->id, $best->subcontractor_id);
    }

    public function test_a_certificate_without_an_end_date_gets_the_usual_validity(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($admin)->post(route('certificates.store', $uitvoerder), [
            'type' => 'BHV',
            'issued_on' => today()->subMonths(2)->toDateString(),
        ])->assertSessionHas('success');

        $certificate = $uitvoerder->certificates()->first();

        // BHV is één jaar geldig, dus tien maanden vanaf nu.
        $this->assertSame(today()->subMonths(2)->addYear()->toDateString(), $certificate->valid_until->toDateString());
        $this->assertSame('ok', $certificate->state());
    }

    public function test_an_expired_certificate_is_reported_as_expired(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $certificate = Certificate::create([
            'user_id' => $uitvoerder->id,
            'type' => 'VCA-basis',
            'valid_until' => today()->subWeek(),
        ]);

        $this->assertSame('verlopen', $certificate->state());
        $this->assertFalse($certificate->isValid());
    }

    public function test_absence_lowers_the_available_capacity_that_week(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $uitvoerder = User::factory()->create([
            'role' => UserRole::Uitvoerder,
            'weekly_capacity' => 40,
        ]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);

        $week = today()->startOfWeek();

        // Twee dagen weg, dus zestien uur minder beschikbaar.
        $uitvoerder->absences()->create([
            'type' => 'vakantie',
            'from' => $week,
            'until' => $week->copy()->addDay(),
        ]);

        ScheduleEntry::factory()->create([
            'user_id' => $uitvoerder->id,
            'project_id' => $project->id,
            'title' => 'Tegelwerk',
            'date' => $week->copy()->addDays(2),
            'end_date' => $week->copy()->addDays(4),
        ]);

        $response = $this->actingAs($leider)->get(route('workforce.index', ['week' => $week->toDateString()]));

        $response->assertOk();
        // Drie dagen ingepland van de 24 beschikbare uren die week.
        $response->assertSee('24 van 24 uur');
    }

    public function test_only_management_edits_personnel_data(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        // Een projectleider mag de lijst zien, maar niet de tarieven aanpassen.
        $this->actingAs($leider)->get(route('workforce.index'))->assertOk();
        $this->actingAs($leider)
            ->patch(route('workforce.update', $uitvoerder), ['hourly_rate' => 999])
            ->assertForbidden();

        $this->actingAs($uitvoerder)->get(route('workforce.index'))->assertForbidden();
    }
}
