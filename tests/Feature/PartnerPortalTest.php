<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Rfq;
use App\Models\Subcontractor;
use App\Models\User;
use App\Support\CertificateCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerPortalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Subcontractor, 1: User} */
    private function partnerWithLogin(bool $complete = true): array
    {
        $user = User::factory()->create([
            'role' => UserRole::Onderaannemer,
            'email' => 'rob@tegelzetters.test',
            'plannable' => false,
        ]);

        $partner = Subcontractor::create([
            'name' => 'Tegelzetters Arnhem',
            'contact_name' => 'Rob Jansen',
            'email' => 'rob@tegelzetters.test',
            'trades' => ['tegelwerk'],
            'id_checked' => $complete,
            'user_id' => $user->id,
        ]);

        if ($complete) {
            foreach (array_keys(CertificateCatalogue::PARTNER_DOCUMENTS) as $type) {
                $partner->documents()->create(['type' => $type, 'valid_until' => today()->addYear()]);
            }

            foreach (array_keys(CertificateCatalogue::PARTNER_AGREEMENTS) as $type) {
                $partner->documents()->create(['type' => $type, 'signed_at' => now()]);
            }
        }

        return [$partner->fresh(['documents']), $user];
    }

    public function test_a_subcontractor_lands_on_the_partner_portal_not_in_our_system(): void
    {
        [, $user] = $this->partnerWithLogin();

        // Vanaf de voordeur word je naar je eigen portaal gestuurd.
        $this->actingAs($user)->get('/')->assertRedirect(route('partner.index'));

        // En de interne omgeving blijft dicht.
        $this->actingAs($user)->get(route('projects.index'))->assertForbidden();
        $this->actingAs($user)->get(route('rfqs.index'))->assertForbidden();
        $this->actingAs($user)->get(route('subcontractors.index'))->assertForbidden();
    }

    public function test_an_incomplete_dossier_is_the_first_thing_a_partner_sees(): void
    {
        [, $user] = $this->partnerWithLogin(complete: false);

        $this->actingAs($user)->get(route('partner.index'))
            ->assertOk()
            ->assertSee('Uw dossier is nog niet compleet')
            ->assertSee('Zolang er iets ontbreekt kunnen wij u geen opdracht geven');
    }

    public function test_a_partner_can_deliver_a_document_himself(): void
    {
        [$partner, $user] = $this->partnerWithLogin(complete: false);

        $this->actingAs($user)->post(route('partner.document', $partner), [
            'type' => 'KvK-uittreksel',
            'valid_until' => today()->addYear()->toDateString(),
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('subcontractor_documents', [
            'subcontractor_id' => $partner->id,
            'type' => 'KvK-uittreksel',
        ]);
    }

    public function test_an_agreement_is_signed_by_delivering_it(): void
    {
        [$partner, $user] = $this->partnerWithLogin(complete: false);

        $this->actingAs($user)->post(route('partner.document', $partner), [
            'type' => 'Modelovereenkomst (DBA)',
        ])->assertSessionHas('success');

        $document = $partner->documents()->firstWhere('type', 'Modelovereenkomst (DBA)');

        $this->assertNotNull($document->signed_at);
        $this->assertSame('Rob Jansen', $document->signed_by);
    }

    public function test_a_partner_cannot_touch_another_partners_dossier(): void
    {
        [, $user] = $this->partnerWithLogin();
        $other = Subcontractor::create(['name' => 'Iemand anders']);

        $this->actingAs($user)->post(route('partner.document', $other), [
            'type' => 'KvK-uittreksel',
        ])->assertForbidden();
    }

    public function test_a_partner_bids_on_a_request_he_was_invited_for(): void
    {
        [$partner, $user] = $this->partnerWithLogin();
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);

        $rfq = Rfq::create([
            'number' => Rfq::nextNumber(),
            'project_id' => $project->id,
            'title' => 'Tegelwerk badkamer',
            'budget' => 4000,
            'status' => Rfq::STATUS_SENT,
            'deadline' => today()->addWeek(),
        ]);

        $rfq->invited()->attach($partner->id, ['invited_at' => now()]);

        $this->actingAs($user)->get(route('partner.index'))
            ->assertOk()
            ->assertSee('Tegelwerk badkamer');

        $this->actingAs($user)->post(route('partner.bid', $rfq), [
            'price' => 3600,
            'can_start_on' => today()->addWeeks(2)->toDateString(),
            'days' => 4,
            'conditions' => 'Exclusief afvoer van puin',
        ])->assertSessionHas('success');

        $bid = $rfq->bids()->first();

        $this->assertSame('3600.00', $bid->price);
        $this->assertSame($partner->id, $bid->subcontractor_id);

        // Nog een keer insturen werkt het bod bij, in plaats van er een tweede te maken.
        $this->actingAs($user)->post(route('partner.bid', $rfq), ['price' => 3400]);

        $this->assertSame(1, $rfq->bids()->count());
        $this->assertSame('3400.00', $bid->fresh()->price);
    }

    public function test_a_partner_cannot_bid_on_a_request_he_was_not_invited_for(): void
    {
        [, $user] = $this->partnerWithLogin();
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);

        $rfq = Rfq::create([
            'number' => Rfq::nextNumber(),
            'project_id' => $project->id,
            'title' => 'Niet voor hem',
            'budget' => 4000,
            'status' => Rfq::STATUS_SENT,
        ]);

        $this->actingAs($user)->post(route('partner.bid', $rfq), ['price' => 100])->assertForbidden();
        $this->assertSame(0, $rfq->bids()->count());
    }

    public function test_bidding_closes_once_the_request_is_no_longer_open(): void
    {
        [$partner, $user] = $this->partnerWithLogin();
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);

        $rfq = Rfq::create([
            'number' => Rfq::nextNumber(),
            'project_id' => $project->id,
            'title' => 'Al gegund',
            'budget' => 4000,
            'status' => Rfq::STATUS_AWARDED,
        ]);

        $rfq->invited()->attach($partner->id, ['invited_at' => now()]);

        $this->actingAs($user)->post(route('partner.bid', $rfq), ['price' => 100])->assertStatus(422);
    }

    public function test_an_account_without_a_partner_record_gets_nothing(): void
    {
        $user = User::factory()->create(['role' => UserRole::Onderaannemer]);

        $this->actingAs($user)->get(route('partner.index'))->assertForbidden();
    }

    public function test_creating_a_partner_login_hands_over_the_password_once(): void
    {
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $partner = Subcontractor::create([
            'name' => 'Stukadoors Nijmegen',
            'contact_name' => 'Ahmed Yilmaz',
            'email' => 'ahmed@stukadoors.test',
        ]);

        $response = $this->actingAs($inkoper)->post(route('subcontractors.portal', $partner));

        $response->assertSessionHas('success');
        $this->assertStringContainsString('ahmed@stukadoors.test', session('success'));

        $partner->refresh();

        $this->assertNotNull($partner->user_id);
        $this->assertSame(UserRole::Onderaannemer, $partner->user->role);
        // Een partnerinlog is geen planbare medewerker.
        $this->assertFalse($partner->user->plannable);
    }

    public function test_a_partner_without_an_email_address_gets_no_login(): void
    {
        $inkoper = User::factory()->create(['role' => UserRole::Inkoper]);
        $partner = Subcontractor::create(['name' => 'Zonder mail']);

        $this->actingAs($inkoper)->post(route('subcontractors.portal', $partner))
            ->assertSessionHas('error');

        $this->assertNull($partner->fresh()->user_id);
    }
}
