<?php

namespace Tests\Feature;

use App\Enums\CallOutcome;
use App\Enums\LeadStatus;
use App\Enums\NextActionType;
use App\Enums\ScheduleEntryType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Models\User;
use App\Services\AgendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * De agenda per medewerker en de belpogingen uit de leadopvolging.
 */
class AgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Woensdagochtend, zodat "over twee uur" en "morgen" allebei gewone werkdagen zijn.
        $this->travelTo(Carbon::parse('2026-11-18 09:30'));
    }

    private function lead(?User $owner = null, array $customer = []): Lead
    {
        return Lead::factory()->create([
            'customer_id' => Customer::factory()->create(['name' => 'Familie Bakker', 'phone' => '0612345678', ...$customer]),
            'assigned_to' => $owner?->id,
            'status' => LeadStatus::Nieuw,
        ]);
    }

    public function test_the_agenda_brings_four_sources_together_for_one_person(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sanne Sales']);
        $collega = User::factory()->create(['role' => UserRole::Sales]);
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'name' => 'Badkamer Jansen']);

        ScheduleEntry::factory()->create([
            'user_id' => $sales->id, 'type' => ScheduleEntryType::Afspraak, 'title' => 'Opname keuken',
            'date' => today(), 'start_time' => '10:00', 'end_time' => '11:00',
        ]);
        ScheduleEntry::factory()->create([
            'user_id' => $sales->id, 'type' => ScheduleEntryType::Project, 'project_id' => $project->id, 'title' => null,
            'date' => today(), 'end_date' => today()->addDay(),
        ]);
        Task::factory()->create(['owner_id' => $sales->id, 'title' => 'Offerte nalopen', 'deadline' => today(), 'due_time' => '14:00']);
        $this->lead($sales)->update(['next_action_at' => today()->setTime(15, 0), 'next_action_type' => NextActionType::Terugbellen]);

        // Van een collega: hoort niet in deze agenda.
        Task::factory()->create(['owner_id' => $collega->id, 'title' => 'Andermans taak', 'deadline' => today(), 'due_time' => '09:00']);

        $items = app(AgendaService::class)->items(collect([$sales]), today(), today());

        $this->assertEqualsCanonicalizing(['afspraak', 'werk', 'taak', 'bellen'], $items->pluck('kind')->all());
        $this->assertSame('Familie Bakker', $items->firstWhere('kind', 'bellen')['title']);
        $this->assertSame('Terugbellen', $items->firstWhere('kind', 'bellen')['label']);

        $this->actingAs($sales)->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('Opname keuken')
            ->assertSee('Badkamer Jansen')
            ->assertSee('Offerte nalopen')
            ->assertSee('Familie Bakker')
            ->assertDontSee('Andermans taak');
    }

    public function test_a_block_of_several_days_shows_on_every_workday_and_participants_see_the_appointment(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $deelnemer = User::factory()->create(['role' => UserRole::Sales]);

        // Vrijdag tot en met maandag: het weekend telt niet mee voor werk.
        ScheduleEntry::factory()->create([
            'user_id' => $leider->id, 'type' => ScheduleEntryType::Project,
            'date' => Carbon::parse('2026-11-20'), 'end_date' => Carbon::parse('2026-11-23'),
        ]);
        $afspraak = ScheduleEntry::factory()->create([
            'user_id' => $leider->id, 'type' => ScheduleEntryType::Afspraak, 'title' => 'Bouwoverleg', 'date' => today(), 'start_time' => '13:00',
        ]);
        $afspraak->participants()->attach($deelnemer->id);

        $agenda = app(AgendaService::class);

        $werk = $agenda->items(collect([$leider]), Carbon::parse('2026-11-16'), Carbon::parse('2026-11-27'))->where('kind', 'werk');
        $this->assertSame(['2026-11-20', '2026-11-23'], $werk->pluck('date')->values()->all());

        $vanDeelnemer = $agenda->items(collect([$deelnemer]), today(), today());
        $this->assertSame('Bouwoverleg', $vanDeelnemer->first()['title']);
        $this->assertSame($deelnemer->id, $vanDeelnemer->first()['user_id']);
    }

    public function test_the_team_view_shows_everyone_and_leads_without_an_owner(): void
    {
        $admin = User::factory()->create(['name' => 'Imad Admin']);
        $sales = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sanne Sales']);
        $this->lead(null)->update(['next_action_at' => today()->setTime(11, 0), 'next_action_type' => NextActionType::Bellen]);

        $this->actingAs($admin)->get(route('agenda.index', ['weergave' => 'team']))
            ->assertOk()
            ->assertSee('Sanne Sales')
            ->assertSee('Niet toegewezen')
            ->assertSee('Belpoging vastleggen voor Familie Bakker');

        // In je eigen agenda staat een aanvraag zonder eigenaar niet op het raster.
        // (De naam staat nog wel in de keuzelijst van het nieuwe-item-venster.)
        $this->actingAs($admin)->get(route('agenda.index', ['weergave' => 'week']))
            ->assertDontSee('Belpoging vastleggen voor Familie Bakker');

        // Een uitvoerder krijgt geen teamweergave en ziet alleen zichzelf.
        $uitvoerder = User::factory()->uitvoerder()->create();
        $this->actingAs($uitvoerder)->get(route('agenda.index', ['weergave' => 'team', 'medewerker' => $sales->id]))
            ->assertOk()
            ->assertDontSee('Niet toegewezen')
            ->assertDontSee('Sanne Sales');
    }

    public function test_no_answer_plans_the_next_attempt_and_counts_the_misses(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $lead = $this->lead();

        $this->actingAs($sales)->post(route('call-attempts.store'), [
            'lead_id' => $lead->id, 'outcome' => 'geen_gehoor', 'retry' => 'plus2',
        ])->assertSessionHas('success');

        $lead->refresh();
        $this->assertSame(1, $lead->missed_calls);
        $this->assertSame(NextActionType::Bellen, $lead->next_action_type);
        $this->assertTrue($lead->next_action_at->equalTo(Carbon::parse('2026-11-18 11:30')));
        // Niet bereikt is geen contact: de aanvraag blijft nieuw.
        $this->assertSame(LeadStatus::Nieuw, $lead->status);
        $this->assertNull($lead->last_contact_at);
        // Wie belde wordt eigenaar, anders staat de volgende poging bij niemand in de agenda.
        $this->assertSame($sales->id, $lead->assigned_to);
        $this->assertDatabaseHas('timeline_events', ['customer_id' => $lead->customer_id, 'title' => 'Geen gehoor, poging 1']);

        // Tweede keer mis: Nova raadt een ander kanaal aan.
        $this->actingAs($sales)->post(route('call-attempts.store'), [
            'lead_id' => $lead->id, 'outcome' => 'voicemail', 'retry' => 'morgen', 'mail_sent' => 1,
        ])->assertSessionHas('error');

        $lead->refresh();
        $this->assertTrue($lead->isUnreached());
        $this->assertTrue($lead->next_action_at->equalTo(Carbon::parse('2026-11-19 10:00')));
        $this->assertStringContainsString('2 keer niet bereikt', $lead->callAdvice());
        $this->assertDatabaseHas('timeline_events', ['customer_id' => $lead->customer_id, 'title' => 'Mail gestuurd na voicemail']);

        $this->actingAs($sales)->get(route('leads.show', $lead))->assertOk()->assertSee('2 keer geen gehoor');
    }

    public function test_a_conversation_resets_the_count_and_moves_the_lead_on(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $lead = $this->lead($sales);
        $lead->update(['missed_calls' => 2]);

        $this->actingAs($sales)->post(route('call-attempts.store'), [
            'lead_id' => $lead->id, 'outcome' => 'gesproken', 'note' => 'Wil een opname volgende week',
            'next_type' => 'afspraak', 'next_date' => '2026-11-24', 'next_time' => '09:00',
        ])->assertSessionHas('success');

        $lead->refresh();
        $this->assertSame(0, $lead->missed_calls);
        $this->assertNull($lead->callAdvice());
        $this->assertSame(LeadStatus::Contact, $lead->status);
        $this->assertNotNull($lead->last_contact_at);
        $this->assertSame(NextActionType::Afspraak, $lead->next_action_type);
        $this->assertTrue($lead->next_action_at->equalTo(Carbon::parse('2026-11-24 09:00')));
    }

    public function test_a_callback_request_needs_a_moment_and_lands_in_the_agenda(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $lead = $this->lead($sales);

        $this->actingAs($sales)->post(route('call-attempts.store'), [
            'lead_id' => $lead->id, 'outcome' => 'terugbelverzoek',
        ])->assertSessionHasErrors('next_date');

        $this->actingAs($sales)->post(route('call-attempts.store'), [
            'lead_id' => $lead->id, 'outcome' => 'terugbelverzoek', 'next_date' => '2026-11-18', 'next_time' => '16:30',
        ])->assertSessionHas('success');

        $this->assertSame(NextActionType::Terugbellen, $lead->fresh()->next_action_type);

        $this->actingAs($sales)->get(route('agenda.index'))
            ->assertSee('Familie Bakker')
            ->assertSee('16:30, Terugbellen', false);
    }

    public function test_retrying_never_lands_outside_working_hours(): void
    {
        // Vrijdag eind van de middag: over twee uur is na zevenen.
        $this->assertTrue(Lead::retryMoment('plus2', Carbon::parse('2026-11-20 17:30'))->equalTo(Carbon::parse('2026-11-23 10:00')));
        $this->assertTrue(Lead::retryMoment('morgen', Carbon::parse('2026-11-20 09:00'))->equalTo(Carbon::parse('2026-11-23 10:00')));
        $this->assertTrue(Lead::retryMoment('plus2', Carbon::parse('2026-11-18 09:30'))->equalTo(Carbon::parse('2026-11-18 11:30')));
        $this->assertFalse(CallOutcome::Gesproken->isMissed());
    }

    public function test_appointments_and_tasks_with_a_time_are_made_from_the_agenda(): void
    {
        $admin = User::factory()->create();
        $collega = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($admin)->post(route('planning.store'), [
            'user_id' => $admin->id, 'type' => 'afspraak', 'title' => 'Kennismaking', 'date' => '2026-11-19',
            'start_time' => '09:00', 'end_time' => '10:00', 'participants' => [$collega->id, $admin->id],
        ])->assertSessionHas('success');

        $entry = ScheduleEntry::firstWhere('title', 'Kennismaking');
        // De eigenaar staat er al op, die hoeft niet nog eens als deelnemer.
        $this->assertSame([$collega->id], $entry->participants->pluck('id')->all());

        $this->actingAs($admin)->post(route('tasks.store'), [
            'title' => 'Tekening opvragen', 'deadline' => '2026-11-19', 'due_time' => '15:00', 'owner_id' => $collega->id,
        ])->assertSessionHas('success');
        $this->assertSame('15:00', substr(Task::firstWhere('title', 'Tekening opvragen')->due_time, 0, 5));

        // Afvinken kan de eigenaar, een deelnemer en wie het werk verdeelt.
        $this->actingAs($collega)->post(route('agenda.done', $entry))->assertSessionHas('success');
        $this->assertNotNull($entry->fresh()->done_at);

        $buitenstaander = User::factory()->uitvoerder()->create();
        $this->actingAs($buitenstaander)->post(route('agenda.done', $entry))->assertForbidden();
    }

    public function test_an_uitvoerder_cannot_log_call_attempts(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $lead = $this->lead();

        $this->actingAs($uitvoerder)->post(route('call-attempts.store'), [
            'lead_id' => $lead->id, 'outcome' => 'gesproken',
        ])->assertForbidden();
    }
}
