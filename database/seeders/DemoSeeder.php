<?php

namespace Database\Seeders;

use App\Enums\ActionSource;
use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Enums\ScheduleEntryType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TimelineEventType;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Quote;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo-data op basis van de voorbeelden uit de technische briefing.
 * Alleen voor lokale ontwikkeling - draait bewust niet op productie.
 * Verwacht dat de teamaccounts al bestaan (TeamSeeder).
 * Zonder factories of Faker (vaste waarden), zodat hij ook draait waar de
 * dev-pakketten ontbreken (`composer install --no-dev`).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command->error('Demo-seeder draait alleen op local/testing-omgevingen.');

            return;
        }

        $imad = User::firstWhere('email', 'imad@renovion.nl');
        $raphael = User::firstWhere('email', 'raphael@renovion.nl');
        $peter = User::firstWhere('email', 'peter@renovion.nl');
        $mehmet = User::firstWhere('email', 'mehmet@renovion.nl');

        $maandag = today()->startOfWeek();

        // --- Familie Jansen: badkamerproject in uitvoering, loopt uit + lead zonder telefoonnummer ---
        $jansen = Customer::create([
            'name' => 'Familie Jansen',
            'email' => 'jansen@example.nl',
            'phone' => null,
            'address' => 'Lindenlaan 12',
            'postal_code' => '3581 CD',
            'city' => 'Utrecht',
        ]);

        $jansenLead = $jansen->leads()->create([
            'service' => 'Complete renovatie',
            'description' => 'Complete renovatie van de benedenverdieping, inclusief stucwerk en schilderwerk.',
            'source' => ActionSource::Website,
            'status' => LeadStatus::Contact,
            'next_action' => 'Telefoonnummer achterhalen en intake plannen',
            'next_action_at' => today()->setTime(9, 0),
            'phone_requested_at' => now()->subDay(),
        ]);

        $jansenProject = Project::create([
            'customer_id' => $jansen->id,
            'name' => 'Badkamer Jansen',
            'address' => $jansen->address,
            'city' => $jansen->city,
            'status' => ProjectStatus::Uitvoering,
            'value' => 18500,
            'deposit_amount' => 5550,
            'deposit_received_at' => now()->subDays(14),
            'paid_amount' => 5550,
            'project_leader_id' => $raphael->id,
            'start_date' => $maandag->copy()->subWeek(),
            'end_date_expected' => today()->subDay(),
            'progress' => 80,
            'notes' => 'Loopt mogelijk 2 dagen uit door latere levering van tegels.',
        ]);
        $jansenProject->craftsmen()->attach($peter);

        // --- Dhr. Ahmed: offerte 5 dagen open, 2x bekeken ---
        $ahmed = Customer::create([
            'name' => 'Dhr. Ahmed',
            'email' => 'ahmed@example.nl',
            'phone' => '06-23456789',
            'address' => 'Westerkade 8',
            'postal_code' => '3016 CL',
            'city' => 'Rotterdam',
        ]);

        $ahmedLead = $ahmed->leads()->create([
            'service' => 'Complete renovatie',
            'description' => 'Complete renovatie appartement, drie kamers en badkamer.',
            'source' => ActionSource::Website,
            'status' => LeadStatus::Offerte,
            'value' => 34850,
            'assigned_to' => $imad->id,
            'last_contact_at' => now()->subDays(6),
        ]);

        $ahmedQuote = Quote::create([
            'number' => Quote::nextNumber(),
            'customer_id' => $ahmed->id,
            'lead_id' => $ahmedLead->id,
            'status' => QuoteStatus::Bekeken,
            'valid_until' => today()->addDays(25)->toDateString(),
            'sent_at' => now()->subDays(5),
            'viewed_at' => now()->subDays(2),
            'viewed_count' => 2,
        ]);
        $ahmedQuote->lines()->createMany([
            ['description' => 'Complete renovatie woonverdieping', 'quantity' => 1, 'unit' => 'post', 'unit_price' => 20000, 'vat_rate' => 21, 'total' => 20000, 'position' => 0],
            ['description' => 'Badkamer: tegelwerk en sanitair', 'quantity' => 1, 'unit' => 'post', 'unit_price' => 8801.65, 'vat_rate' => 21, 'total' => 8801.65, 'position' => 1],
        ]);
        $ahmedQuote->recalculateTotals();

        // --- Familie de Vries: terugbelafspraak om 11:00 + project gepland op vrijdag ---
        $deVries = Customer::create([
            'name' => 'Familie de Vries',
            'email' => 'devries@example.nl',
            'phone' => '06-34567890',
            'address' => 'Dorpsstraat 45',
            'postal_code' => '6721 JK',
            'city' => 'Bennekom',
        ]);

        $deVries->leads()->create([
            'service' => 'Stucwerk',
            'description' => 'Stucwerk woonkamer en hal.',
            'source' => ActionSource::Handmatig,
            'status' => LeadStatus::Akkoord,
            'assigned_to' => $imad->id,
            'next_action' => 'Terugbelafspraak',
            'next_action_at' => today()->setTime(11, 0),
        ]);

        $deVriesProject = Project::create([
            'customer_id' => $deVries->id,
            'name' => 'Stucwerk De Vries',
            'address' => $deVries->address,
            'city' => $deVries->city,
            'status' => ProjectStatus::Gepland,
            'value' => 6800,
            'deposit_amount' => 2040,
            'deposit_received_at' => now()->subDays(3),
            'project_leader_id' => $raphael->id,
            'start_date' => $maandag->copy()->addDays(4),
            'end_date_expected' => $maandag->copy()->addDays(11),
        ]);
        $deVriesProject->craftsmen()->attach($peter);

        // --- Familie Bakker: aanbetaling nog niet ontvangen, start over 4 dagen ---
        $bakker = Customer::create([
            'name' => 'Familie Bakker',
            'email' => 'bakker@example.nl',
            'phone' => '06-45678901',
            'address' => 'Parkweg 3',
            'postal_code' => '6811 KS',
            'city' => 'Arnhem',
        ]);

        $bakkerProject = Project::create([
            'customer_id' => $bakker->id,
            'name' => 'Complete renovatie Bakker',
            'address' => $bakker->address,
            'city' => $bakker->city,
            'status' => ProjectStatus::Gepland,
            'value' => 52000,
            'deposit_amount' => 15600,
            'deposit_received_at' => null,
            'project_leader_id' => $imad->id,
            'start_date' => today()->addDays(4),
            'end_date_expected' => today()->addDays(46),
        ]);
        $bakkerProject->craftsmen()->attach($mehmet);

        // --- Familie Smit: uitvoering, materiaal vertraagd ---
        $smit = Customer::create([
            'name' => 'Familie Smit',
            'email' => 'smit@example.nl',
            'phone' => '06-56789012',
            'address' => 'Beukenhof 21',
            'postal_code' => '6821 AB',
            'city' => 'Arnhem',
        ]);

        $smitProject = Project::create([
            'customer_id' => $smit->id,
            'name' => 'Stucwerk Smit',
            'address' => $smit->address,
            'city' => $smit->city,
            'status' => ProjectStatus::Uitvoering,
            'value' => 9400,
            'deposit_amount' => 2820,
            'deposit_received_at' => now()->subDays(15),
            'paid_amount' => 2820,
            'project_leader_id' => $raphael->id,
            'start_date' => $maandag->copy()->subDays(5),
            'end_date_expected' => $maandag->copy()->addDays(18),
            'progress' => 40,
            'notes' => 'Materiaal vertraagd: levering stucmateriaal verwacht +2 dagen.',
        ]);
        $smitProject->craftsmen()->attach($mehmet);

        // --- Nieuwe websiteaanvragen (3) ---
        foreach ([
            ['name' => 'Familie Visser', 'email' => 'visser@example.nl', 'address' => 'Groesbeekseweg 140', 'postal_code' => '6524 DL', 'city' => 'Nijmegen', 'service' => 'Badkamerrenovatie', 'phone' => '06-67890123'],
            ['name' => 'Familie van den Berg', 'email' => 'vandenberg@example.nl', 'address' => 'Molenstraat 7', 'postal_code' => '6711 AW', 'city' => 'Ede', 'service' => 'Schilderwerk', 'phone' => null],
            ['name' => 'Mevr. El Amrani', 'email' => 'elamrani@example.nl', 'address' => 'Klarendalseweg 55', 'postal_code' => '6822 GC', 'city' => 'Arnhem', 'service' => 'Toiletrenovatie', 'phone' => '06-78901234'],
        ] as $nieuw) {
            $customer = Customer::create(collect($nieuw)->except('service')->all());
            $lead = $customer->leads()->create([
                'service' => $nieuw['service'],
                'description' => 'Websiteaanvraag via renovion.nl.',
                'source' => ActionSource::Website,
                'status' => LeadStatus::Nieuw,
            ]);
            $customer->timelineEvents()->create([
                'type' => TimelineEventType::Aanvraag,
                'source' => ActionSource::Website,
                'title' => 'Websiteaanvraag: '.$nieuw['service'],
                'subject_type' => 'lead',
                'subject_id' => $lead->id,
                'happened_at' => now()->subHours(rand(2, 20)),
            ]);
        }

        // --- Taken (Nu doen) ---
        $taak = fn (array $attributes) => Task::create([
            'deadline' => today()->toDateString(),
            'priority' => TaskPriority::Normaal,
            'status' => TaskStatus::TeDoen,
            'source' => ActionSource::Handmatig,
            ...$attributes,
        ]);
        $taak([
            'title' => 'Aanbetaling Bakker controleren',
            'customer_id' => $bakker->id,
            'project_id' => $bakkerProject->id,
            'owner_id' => $imad->id,
            'priority' => TaskPriority::Hoog,
        ]);
        $taak([
            'title' => 'Offerte Ahmed opvolgen (5 dagen open)',
            'customer_id' => $ahmed->id,
            'lead_id' => $ahmedLead->id,
            'owner_id' => $imad->id,
            'priority' => TaskPriority::Hoog,
        ]);
        $taak([
            'title' => 'Materiaalleverancier Smit nabellen',
            'customer_id' => $smit->id,
            'project_id' => $smitProject->id,
            'owner_id' => $raphael->id,
        ]);
        $taak([
            'title' => 'Telefoonnummer familie Jansen opvragen',
            'customer_id' => $jansen->id,
            'lead_id' => $jansenLead->id,
            'owner_id' => $raphael->id,
            'source' => ActionSource::Automation,
        ]);

        // --- Planning deze week (briefing: Peter → Jansen ma-do, De Vries vr; Mehmet → Bakker ma-di, Smit wo-vr) ---
        foreach (range(0, 3) as $offset) {
            ScheduleEntry::create([
                'user_id' => $peter->id,
                'project_id' => $jansenProject->id,
                'customer_id' => $jansen->id,
                'type' => ScheduleEntryType::Project,
                'date' => $maandag->copy()->addDays($offset),
            ]);
        }
        ScheduleEntry::create([
            'user_id' => $peter->id,
            'project_id' => $deVriesProject->id,
            'customer_id' => $deVries->id,
            'type' => ScheduleEntryType::Project,
            'date' => $maandag->copy()->addDays(4),
        ]);
        foreach (range(0, 1) as $offset) {
            ScheduleEntry::create([
                'user_id' => $mehmet->id,
                'project_id' => $bakkerProject->id,
                'customer_id' => $bakker->id,
                'type' => ScheduleEntryType::Project,
                'date' => $maandag->copy()->addDays($offset),
            ]);
        }
        foreach (range(2, 4) as $offset) {
            ScheduleEntry::create([
                'user_id' => $mehmet->id,
                'project_id' => $smitProject->id,
                'customer_id' => $smit->id,
                'type' => ScheduleEntryType::Project,
                'date' => $maandag->copy()->addDays($offset),
            ]);
        }
        ScheduleEntry::create([
            'user_id' => $imad->id,
            'customer_id' => $deVries->id,
            'type' => ScheduleEntryType::Terugbel,
            'title' => 'Terugbelafspraak De Vries',
            'date' => today(),
            'start_time' => '11:00',
        ]);

        // --- Timeline-dossiers vullen ---
        $jansen->timelineEvents()->createMany([
            ['type' => TimelineEventType::Aanvraag, 'source' => ActionSource::Website, 'title' => 'Websiteaanvraag: complete renovatie', 'subject_type' => 'lead', 'subject_id' => $jansenLead->id, 'happened_at' => now()->subDays(9)],
            ['type' => TimelineEventType::Email, 'source' => ActionSource::Automation, 'title' => 'E-mail verstuurd: telefoonnummer opgevraagd', 'happened_at' => now()->subDay()],
            ['type' => TimelineEventType::Projectupdate, 'source' => ActionSource::Handmatig, 'user_id' => $raphael->id, 'title' => 'Badkamer Jansen: tegels later geleverd, mogelijk 2 dagen uitloop', 'subject_type' => 'project', 'subject_id' => $jansenProject->id, 'happened_at' => now()->subHours(20)],
        ]);

        $ahmed->timelineEvents()->createMany([
            ['type' => TimelineEventType::Aanvraag, 'source' => ActionSource::Website, 'title' => 'Websiteaanvraag: complete renovatie', 'subject_type' => 'lead', 'subject_id' => $ahmedLead->id, 'happened_at' => now()->subDays(12)],
            ['type' => TimelineEventType::Telefoon, 'source' => ActionSource::Handmatig, 'user_id' => $imad->id, 'title' => 'Intakegesprek gevoerd', 'body' => 'Wensen doorgenomen, opmeting gepland.', 'happened_at' => now()->subDays(8)],
            ['type' => TimelineEventType::Offerte, 'source' => ActionSource::Handmatig, 'user_id' => $imad->id, 'title' => "Offerte {$ahmedQuote->number} verstuurd", 'subject_type' => 'quote', 'subject_id' => $ahmedQuote->id, 'happened_at' => now()->subDays(5)],
        ]);

        $bakker->timelineEvents()->createMany([
            ['type' => TimelineEventType::Offerte, 'source' => ActionSource::Handmatig, 'user_id' => $imad->id, 'title' => 'Offerte akkoord - project aangemaakt', 'subject_type' => 'project', 'subject_id' => $bakkerProject->id, 'happened_at' => now()->subDays(10)],
            ['type' => TimelineEventType::Betaling, 'source' => ActionSource::Handmatig, 'user_id' => $imad->id, 'title' => 'Aanbetalingsfactuur € 15.600 verstuurd', 'happened_at' => now()->subDays(9)],
        ]);
    }
}
