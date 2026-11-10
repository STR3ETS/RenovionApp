<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Intake;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_an_intake_moves_a_new_lead_to_contact(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $lead = Lead::factory()->create([
            'customer_id' => Customer::factory(),
            'status' => LeadStatus::Nieuw,
            'service' => 'Badkamerrenovatie',
        ]);

        $this->actingAs($sales)->post(route('intakes.store'), ['lead_id' => $lead->id])->assertRedirect();

        $intake = Intake::firstWhere('lead_id', $lead->id);

        $this->assertNotNull($intake);
        $this->assertSame(1, $intake->step);
        $this->assertSame(LeadStatus::Contact, $lead->fresh()->status);

        // Een tweede keer starten levert dezelfde intake op.
        $this->actingAs($sales)->post(route('intakes.store'), ['lead_id' => $lead->id]);
        $this->assertSame(1, Intake::where('lead_id', $lead->id)->count());
    }

    public function test_the_completeness_score_counts_the_questions_a_calculator_needs(): void
    {
        $customer = Customer::factory()->create(['phone' => '0612345678']);
        $intake = Intake::create(['customer_id' => $customer->id, 'answers' => []]);

        // Acht punten: zeven vragen plus het telefoonnummer. Alleen dat nummer is er.
        $this->assertSame(13, $intake->completeness()['percentage']);
        $this->assertCount(7, $intake->completeness()['missing']);

        $intake->update(['answers' => array_fill_keys(Intake::REQUIRED, 'antwoord')]);

        $this->assertSame(100, $intake->fresh()->completeness()['percentage']);
        $this->assertSame([], $intake->fresh()->completeness()['missing']);
    }

    public function test_a_missing_phone_number_counts_against_the_score(): void
    {
        $customer = Customer::factory()->create(['phone' => null]);
        $intake = Intake::create([
            'customer_id' => $customer->id,
            'answers' => array_fill_keys(Intake::REQUIRED, 'antwoord'),
        ]);

        $completeness = $intake->completeness();

        // Zonder nummer kun je geen afspraak maken, dus telt het mee.
        $this->assertSame(88, $completeness['percentage']);
        $this->assertContains('telefoonnummer van de klant', $completeness['missing']);
    }

    public function test_nova_asks_the_questions_that_fit_the_work(): void
    {
        $lead = Lead::factory()->create([
            'customer_id' => Customer::factory(),
            'service' => 'Badkamerrenovatie',
        ]);
        $intake = Intake::create(['lead_id' => $lead->id, 'answers' => []]);

        $questions = $intake->suggestedQuestions();

        $this->assertContains('Waar ligt de huidige afvoer, en kan de douche verplaatst worden?', $questions);
        $this->assertContains('Moet er tijdens de verbouwing een tijdelijke douche komen?', $questions);

        // Met alles ingevuld blijft er alleen een vraag over het soort werk staan.
        $intake->update(['answers' => array_fill_keys(array_keys(Intake::FIELDS), 'antwoord, afvoer zit links')]);
        $questions = $intake->fresh()->suggestedQuestions();

        $this->assertNotContains('Waar ligt de huidige afvoer, en kan de douche verplaatst worden?', $questions);
    }

    public function test_an_intake_cannot_be_finished_with_core_questions_missing(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $customer = Customer::factory()->create(['phone' => '0612345678']);
        $intake = Intake::create(['customer_id' => $customer->id, 'answers' => ['wensen' => 'Nieuwe badkamer']]);

        $this->actingAs($sales)->post(route('intakes.complete', $intake))->assertSessionHas('error');
        $this->assertFalse($intake->fresh()->isComplete());

        $intake->update(['answers' => array_fill_keys(Intake::REQUIRED, 'antwoord')]);

        $this->actingAs($sales)->post(route('intakes.complete', $intake))->assertSessionHas('success');
        $this->assertTrue($intake->fresh()->isComplete());
    }

    public function test_planning_the_survey_puts_it_in_the_planning(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $opnemer = User::factory()->create(['role' => UserRole::Projectleider]);
        $lead = Lead::factory()->create([
            'customer_id' => Customer::factory(),
            'status' => LeadStatus::Contact,
        ]);
        $intake = Intake::create(['lead_id' => $lead->id, 'customer_id' => $lead->customer_id, 'answers' => []]);

        $this->actingAs($sales)->post(route('intakes.survey', $intake), [
            'surveyor_id' => $opnemer->id,
            'survey_on' => today()->addDays(3)->toDateString(),
            'survey_at' => '10:30',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('schedule_entries', [
            'user_id' => $opnemer->id,
            'lead_id' => $lead->id,
            'type' => 'intake',
        ]);

        // De aanvraag schuift door naar de fase intake.
        $this->assertSame(LeadStatus::Intake, $lead->fresh()->status);
        $this->assertSame(3, $intake->fresh()->step);
    }

    public function test_the_scoring_chance_follows_what_the_customer_does(): void
    {
        $dashboard = new QuoteDashboard;

        $maak = function (int $views, int $questions) {
            $quote = Quote::factory()->create([
                'customer_id' => Customer::factory(),
                'status' => QuoteStatus::Verstuurd,
                'sent_at' => now()->subDays(3),
                'total' => 10000,
            ]);

            for ($i = 0; $i < $views; $i++) {
                $quote->views()->create(['viewed_at' => now()->subDays(2 - $i)]);
            }

            for ($i = 0; $i < $questions; $i++) {
                $quote->questions()->create(['body' => 'Kan de douche ook links?']);
            }

            return Quote::withCount(['views', 'questions'])->find($quote->id);
        };

        $this->assertSame(QuoteDashboard::CHANCE_UNOPENED, $dashboard->chance($maak(0, 0)));
        $this->assertSame(QuoteDashboard::CHANCE_OPENED, $dashboard->chance($maak(1, 0)));
        $this->assertSame(QuoteDashboard::CHANCE_REPEAT_VIEW, $dashboard->chance($maak(2, 0)));
        // Een vraag weegt zwaarder dan hoe vaak iemand kijkt.
        $this->assertSame(QuoteDashboard::CHANCE_QUESTIONS, $dashboard->chance($maak(1, 1)));
    }

    public function test_a_quote_without_a_decision_expires_after_thirty_days(): void
    {
        $dashboard = new QuoteDashboard;

        $recent = Quote::factory()->create([
            'customer_id' => Customer::factory(),
            'status' => QuoteStatus::Verstuurd,
            'sent_at' => now()->subDays(10),
        ]);

        $oud = Quote::factory()->create([
            'customer_id' => Customer::factory(),
            'status' => QuoteStatus::Verstuurd,
            'sent_at' => now()->subDays(45),
        ]);

        $getekend = Quote::factory()->create([
            'customer_id' => Customer::factory(),
            'status' => QuoteStatus::Akkoord,
            'sent_at' => now()->subDays(60),
            'accepted_at' => now()->subDays(50),
        ]);

        $this->assertSame('open', $dashboard->state($recent));
        $this->assertSame('verlopen', $dashboard->state($oud));
        // Getekend blijft getekend, ook na dertig dagen.
        $this->assertSame('getekend', $dashboard->state($getekend));
    }

    public function test_the_dashboard_separates_hit_rate_on_count_from_hit_rate_on_money(): void
    {
        $directie = User::factory()->create(['role' => UserRole::Directie]);

        // Eén grote gewonnen, twee kleine verloren: op aantal 33 procent, op geld 80.
        Quote::factory()->create([
            'customer_id' => Customer::factory(),
            'status' => QuoteStatus::Akkoord,
            'sent_at' => now()->subDays(20),
            'accepted_at' => now()->subDays(15),
            'total' => 40000,
        ]);

        foreach ([5000, 5000] as $bedrag) {
            Quote::factory()->create([
                'customer_id' => Customer::factory(),
                'status' => QuoteStatus::Afgewezen,
                'sent_at' => now()->subDays(20),
                'total' => $bedrag,
            ]);
        }

        $data = (new QuoteDashboard)->build('jaar');

        $this->assertSame(33.3, $data['hit_rate']);
        $this->assertSame(80.0, $data['hit_rate_value']);

        $this->actingAs($directie)->get(route('quote-dashboard'))
            ->assertOk()
            ->assertSee('Hitrate op aantal');
    }

    public function test_opening_the_customer_link_records_every_visit(): void
    {
        $quote = Quote::factory()->create([
            'customer_id' => Customer::factory(),
            'status' => QuoteStatus::Verstuurd,
            'sent_at' => now()->subDay(),
        ]);

        $this->get(route('quotes.public', $quote->public_token))->assertOk();
        $this->get(route('quotes.public', $quote->public_token))->assertOk();

        $this->assertSame(2, $quote->views()->count());
        $this->assertSame(2, $quote->fresh()->viewed_count);
    }

    public function test_follow_up_sorts_the_most_urgent_first(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        Lead::factory()->create([
            'customer_id' => Customer::factory()->create(['name' => 'Familie Later']),
            'status' => LeadStatus::Contact,
            'next_action_at' => today()->addWeek(),
        ]);

        Lead::factory()->create([
            'customer_id' => Customer::factory()->create(['name' => 'Familie TeLaat']),
            'status' => LeadStatus::Contact,
            'next_action_at' => today()->subDays(3),
        ]);

        Lead::factory()->create([
            'customer_id' => Customer::factory()->create(['name' => 'Familie Niets']),
            'status' => LeadStatus::Contact,
            'next_action_at' => null,
        ]);

        $response = $this->actingAs($sales)->get(route('follow-ups.index'));

        $response->assertOk();
        // Te laat bovenaan, dan niets gepland, dan later.
        $response->assertSeeInOrder(['Familie TeLaat', 'Familie Niets', 'Familie Later']);
    }

    public function test_a_craftsman_cannot_reach_the_sales_screens(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get(route('intakes.index'))->assertForbidden();
        $this->actingAs($uitvoerder)->get(route('follow-ups.index'))->assertForbidden();
        $this->actingAs($uitvoerder)->get(route('quote-dashboard'))->assertForbidden();
    }
}
