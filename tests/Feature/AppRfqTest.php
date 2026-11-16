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

/** Prijsaanvragen als eigen scherm in de app: lijst, aanvraag, uitnodigen, bieden, gunnen en opslaan. */
class AppRfqTest extends TestCase
{
    use RefreshDatabase;

    private function token(UserRole $role = UserRole::Admin): string
    {
        return User::factory()->create(['role' => $role])->createToken('test')->plainTextToken;
    }

    private function partner(string $name = 'Tegelzetters Arnhem', bool $approved = true): Subcontractor
    {
        $partner = Subcontractor::create(['name' => $name, 'id_checked' => $approved]);

        if ($approved) {
            foreach (array_keys(CertificateCatalogue::PARTNER_DOCUMENTS) as $type) {
                $partner->documents()->create(['type' => $type, 'valid_until' => today()->addYear()]);
            }

            foreach (array_keys(CertificateCatalogue::PARTNER_AGREEMENTS) as $type) {
                $partner->documents()->create(['type' => $type, 'signed_at' => now()]);
            }
        }

        return $partner->fresh(['documents']);
    }

    private function rfq(array $attributes = []): Rfq
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'name' => 'Badkamer Velp']);

        return Rfq::create([
            'number' => Rfq::nextNumber(),
            'project_id' => $project->id,
            'title' => 'Tegelwerk badkamer',
            'budget' => 4000,
            'status' => Rfq::STATUS_DRAFT,
            ...$attributes,
        ]);
    }

    public function test_the_list_shows_commitments_tabs_and_each_request(): void
    {
        $partner = $this->partner();
        $awarded = $this->rfq(['status' => Rfq::STATUS_AWARDED, 'awarded_to' => $partner->id]);
        $awarded->bids()->create(['subcontractor_id' => $partner->id, 'price' => 3600, 'completeness' => 100]);
        $this->rfq(['title' => 'Stucwerk', 'status' => Rfq::STATUS_SENT, 'deadline' => today()->subDay()]);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.rfqs.index'))->assertOk()
            ->assertJsonPath('kpis.commitment', 3600)
            ->assertJsonPath('kpis.savings', 400)
            ->assertJsonPath('kpis.savings_label', 'Onder begroting')
            ->assertJsonPath('kpis.sent', 1)
            ->assertJsonPath('tabs.*.value', ['', 'uitgezet', 'gegund'])
            ->assertJsonPath('rfqs.0.title', 'Stucwerk')
            ->assertJsonPath('rfqs.0.overdue', true)
            ->assertJsonPath('rfqs.1.winner.name', 'Tegelzetters Arnhem')
            ->assertJsonPath('rfqs.1.path', route('rfqs.show', $awarded, false))
            ->assertJsonPath('links.create', route('rfqs.create', [], false));

        $this->withToken($token)->getJson(route('api.app.rfqs.index', ['status' => 'gegund']))->assertOk()
            ->assertJsonCount(1, 'rfqs')
            ->assertJsonPath('rfqs.0.id', $awarded->id);
    }

    public function test_inviting_bidding_and_awarding_from_the_app(): void
    {
        $rfq = $this->rfq();
        $good = $this->partner();
        $bad = $this->partner('Papieren niet in orde', approved: false);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.rfqs.show', $rfq))->assertOk()
            ->assertJsonPath('can_invite', true)
            ->assertJsonPath('can_bid', false)
            ->assertJsonPath('candidates.*.name', ['Papieren niet in orde', 'Tegelzetters Arnhem'])
            ->assertJsonPath('candidates.0.warning', 'Dossier incompleet');

        $this->withToken($token)->postJson(route('api.app.rfqs.invite', $rfq), ['subcontractor_ids' => [$good->id, $bad->id]])
            ->assertOk()
            ->assertJsonPath('refused', ['Papieren niet in orde']);
        $this->assertSame(Rfq::STATUS_SENT, $rfq->fresh()->status);

        $this->withToken($token)->postJson(route('api.app.rfqs.bids', $rfq), ['subcontractor_id' => $good->id, 'price' => 3600, 'completeness' => 100])
            ->assertOk()->assertJsonPath('message', 'Bieding vastgelegd.');

        $show = $this->withToken($token)->getJson(route('api.app.rfqs.show', $rfq))->assertOk()
            ->assertJsonPath('can_bid', true)
            ->assertJsonPath('invited.0.bid', true)
            ->assertJsonPath('bids.0.partner', 'Tegelzetters Arnhem')
            ->assertJsonPath('bids.0.variance', -10)
            ->assertJsonPath('bids.0.best', true);

        $this->withToken($token)->postJson(route('api.app.rfqs.award', [$rfq, $show->json('bids.0.id')]))->assertOk();

        $this->withToken($token)->getJson(route('api.app.rfqs.show', $rfq))->assertOk()
            ->assertJsonPath('status.value', 'gegund')
            ->assertJsonPath('winner.name', 'Tegelzetters Arnhem')
            ->assertJsonPath('winner.savings', 400)
            ->assertJsonPath('winner.under', true)
            ->assertJsonPath('can_award', false)
            ->assertJsonPath('can_invite', false);
    }

    public function test_awarding_to_a_partner_with_an_open_dossier_is_refused_with_a_message(): void
    {
        $rfq = $this->rfq(['status' => Rfq::STATUS_SENT]);
        $bid = $rfq->bids()->create(['subcontractor_id' => $this->partner('Zonder papieren', approved: false)->id, 'price' => 3000]);

        $this->withToken($this->token())->postJson(route('api.app.rfqs.award', [$rfq, $bid]))
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'is niet rond'));
        $this->assertSame(Rfq::STATUS_SENT, $rfq->fresh()->status);
    }

    public function test_saving_the_request_goes_through_the_website_rules(): void
    {
        $rfq = $this->rfq();
        $token = $this->token();

        $this->withToken($token)->patchJson(route('api.app.rfqs.update', $rfq), [
            'budget' => 4500, 'deadline' => today()->addWeek()->toDateString(), 'starts_on' => null, 'scope' => 'Wand- en vloertegels',
        ])->assertOk()
            ->assertJsonPath('message', 'Prijsaanvraag bijgewerkt.')
            ->assertJsonPath('form.budget', 4500)
            ->assertJsonPath('scope', 'Wand- en vloertegels');
        $this->assertSame('4500.00', $rfq->fresh()->budget);

        $this->withToken($token)->patchJson(route('api.app.rfqs.update', $rfq), ['budget' => 'veel'])
            ->assertUnprocessable()->assertJsonValidationErrors('budget');
    }

    public function test_executors_cannot_open_price_requests(): void
    {
        $rfq = $this->rfq();
        $token = $this->token(UserRole::Uitvoerder);

        $this->withToken($token)->getJson(route('api.app.rfqs.index'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.rfqs.show', $rfq))->assertForbidden();
    }
}
