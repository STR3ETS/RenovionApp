<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\JobReportType;
use App\Enums\JobStatus;
use App\Enums\ScheduleEntryType;
use App\Enums\WorkOrderStatus;
use App\Models\AgentApproval;
use App\Models\AgentEvent;
use App\Models\AgentRun;
use App\Models\Budget;
use App\Models\BudgetOption;
use App\Models\BudgetTerm;
use App\Models\Intake;
use App\Models\Invoice;
use App\Models\JobExecution;
use App\Models\JobReport;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\QualityReview;
use App\Models\Rfq;
use App\Models\ScheduleEntry;
use App\Models\ServiceTicket;
use App\Models\SnagItem;
use App\Models\Subcontractor;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\BudgetChapterLibrary;
use App\Support\CertificateCatalogue;
use Illuminate\Database\Seeder;

/**
 * Demodata voor de modules die na de eerste briefing zijn gebouwd: begrotingen,
 * klussen, werkbonnen, geld, partners, kwaliteit, nazorg en de agents.
 *
 * Draait bovenop DemoSeeder en gaat uit van de projecten die daar gemaakt zijn.
 * Alleen op local en testing, net als de andere demodata.
 */
class RenovionOsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command->error('Deze seeder draait alleen op local en testing.');

            return;
        }

        $jansen = Project::firstWhere('name', 'Badkamer Jansen');
        $bakker = Project::firstWhere('name', 'Complete renovatie Bakker');

        if ($jansen === null || $bakker === null) {
            $this->command->error('Draai eerst DemoSeeder: de projecten uit de briefing ontbreken.');

            return;
        }

        $raphael = User::firstWhere('email', 'raphael@renovion.nl');
        $peter = User::firstWhere('email', 'peter@renovion.nl');
        $mehmet = User::firstWhere('email', 'mehmet@renovion.nl');

        $this->people($peter, $mehmet);
        $budget = $this->budget($jansen, $raphael);
        $this->money($jansen, $budget, $raphael);
        $this->jobs($jansen, $peter);
        $this->partners($jansen);
        $this->quality($jansen, $bakker, $raphael, $peter);
        $this->agents($jansen, $raphael);
        $this->intake($jansen);

        $this->command->info('Renovion OS-demodata toegevoegd.');
    }

    /** Certificaten, tarieven en een week vakantie. */
    private function people(?User $peter, ?User $mehmet): void
    {
        foreach (array_filter([$peter, $mehmet]) as $index => $person) {
            $person->update([
                'job_title' => $index === 0 ? 'Uitvoerder tegelwerk' : 'Uitvoerder stucwerk',
                'trades' => $index === 0 ? ['tegelwerk', 'loodgieterswerk'] : ['stucwerk', 'schilderwerk'],
                'hourly_rate' => $index === 0 ? 52 : 48,
                'weekly_capacity' => 40,
            ]);

            // Eén certificaat dat binnenkort verloopt, zodat het signaal zichtbaar is.
            $person->certificates()->firstOrCreate(['type' => 'VCA-basis'], [
                'valid_until' => $index === 0 ? today()->addDays(18) : today()->addYears(4),
            ]);

            $person->certificates()->firstOrCreate(['type' => 'BHV'], [
                'valid_until' => today()->addMonths(7),
            ]);
        }

        $mehmet?->absences()->firstOrCreate([
            'from' => today()->addWeeks(2)->startOfWeek(),
        ], [
            'type' => 'vakantie',
            'until' => today()->addWeeks(2)->startOfWeek()->addDays(4),
            'note' => 'Week vrij',
        ]);
    }

    /** Een definitieve begroting met hoofdstukken, regels, staart en termijnen. */
    private function budget(Project $project, ?User $author): Budget
    {
        $existing = Budget::firstWhere('project_id', $project->id);

        if ($existing !== null) {
            return $existing;
        }

        $budget = Budget::create([
            'number' => Budget::nextNumber(),
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'name' => 'Begroting '.$project->name,
            'status' => Budget::STATUS_FINAL,
            'hourly_rate' => 55,
            'vat_pct' => 21,
            'floor_area' => 6.5,
            'location' => collect([$project->address, $project->city])->filter()->join(', '),
            'author' => $author?->name,
            'dated_on' => today()->subWeeks(3),
            'scope' => [
                'docs' => ['Opnamerapport 14 oktober', 'Fotos bestaande situatie'],
                'incl' => ['Sloopwerk en afvoer', 'Waterdichting volgens norm', 'Tegelwerk wand en vloer', 'Sanitair plaatsen'],
                'excl' => ['Sanitair zelf, wordt door de klant geleverd', 'Vloerverwarming', 'Schilderwerk plafond'],
            ],
            'finalised_at' => now()->subWeeks(3),
            'finalised_by' => $author?->id,
            'created_by' => $author?->id,
        ]);

        foreach (Budget::DEFAULT_MARKUPS as $position => $markup) {
            $budget->markups()->create([...$markup, 'position' => $position]);
        }

        foreach (BudgetChapterLibrary::defaultTerms() as $position => $term) {
            $budget->terms()->create([...$term, 'position' => $position]);
        }

        // Vier hoofdstukken met echte regels, zodat de doorrekening iets toont.
        $lines = [
            ['10', 'Sloopwerk badkamer', [
                ['Sloopwerk tegels en sanitair', 6.5, 'm2', 1.2, 0, 0, 0, 0],
                ['Afvoeren bouwafval, container', 1, 'pst', 0, 0, 0, 0, 340],
            ]],
            ['52', 'Leidingwerk', [
                ['Waterleiding aanpassen', 1, 'pst', 6, 180, 0, 0, 0],
                ['Afvoer verplaatsen', 1, 'pst', 4, 95, 0, 0, 0],
            ]],
            ['41', 'Tegelwerk', [
                ['Waterdichting aanbrengen', 14, 'm2', 0.35, 18.50, 0, 0, 0],
                ['Wandtegels zetten', 22, 'm2', 0.75, 32.50, 1.25, 0, 0],
                ['Vloertegels zetten', 6.5, 'm2', 0.9, 44, 1.25, 0, 0],
                ['Voegen en kitten', 28.5, 'm2', 0.2, 4.80, 0, 0, 0],
            ]],
            ['74', 'Sanitair plaatsen', [
                ['Sanitair monteren', 1, 'pst', 8, 0, 0, 0, 0],
                ['Inloopdouche met glaswand', 1, 'pst', 3, 0, 0, 980, 0],
            ]],
        ];

        $position = 0;

        foreach ($lines as [$code, $paragraphName, $rows]) {
            $chapter = $budget->chapters()->create([
                'code' => $code,
                'name' => BudgetChapterLibrary::CHAPTERS[$code] ?? $code,
                'position' => $position++,
            ]);

            $paragraph = $chapter->paragraphs()->create(['code' => '.01', 'name' => $paragraphName]);

            foreach ($rows as $index => [$description, $quantity, $unit, $hours, $material, $equipment, $sub, $other]) {
                $paragraph->lines()->create([
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'norm_hours' => $hours,
                    'material' => $material,
                    'equipment' => $equipment,
                    'subcontracting' => $sub,
                    'other' => $other,
                    'source' => 'Eigen norm Renovion',
                    'position' => $index,
                ]);
            }
        }

        // Eén open optie en één goedgekeurd stuk meerwerk.
        $budget->options()->create([
            'description' => 'Vloerverwarming elektrisch',
            'note' => 'Klant denkt er nog over',
            'quantity' => 6.5,
            'unit' => 'm2',
            'price' => 78,
            'status' => BudgetOption::STATUS_OPEN,
        ]);

        $budget->options()->create([
            'description' => 'Twee extra wandcontactdozen',
            'quantity' => 2,
            'unit' => 'st',
            'price' => 85,
            'status' => BudgetOption::STATUS_APPROVED,
            'decided_at' => now()->subDays(4),
        ]);

        return $budget;
    }

    /** Twee facturen, waarvan een over de vervaldatum, plus inkoop. */
    private function money(Project $project, Budget $budget, ?User $user): void
    {
        if (Invoice::where('project_id', $project->id)->exists()) {
            return;
        }

        $totals = $budget->totals();
        $terms = $budget->terms;

        // Eerste termijn betaald, tweede verstuurd en over de vervaldatum.
        $first = Invoice::create([
            'number' => Invoice::nextNumber(),
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'budget_term_id' => $terms[0]->id,
            'type' => InvoiceType::Termijn,
            'description' => $terms[0]->description,
            'date' => today()->subDays(24),
            'due_date' => today()->subDays(10),
            'amount' => $budget->termAmount($terms[0], $totals),
            'vat_pct' => $totals->vatPercentage,
            'status' => InvoiceStatus::Betaald,
            'sent_at' => now()->subDays(24),
            'paid_at' => now()->subDays(12),
        ]);

        $first->update(['paid_amount' => $first->total()]);
        $first->lines()->create(['description' => $terms[0]->description, 'unit_price' => $first->amount]);
        $terms[0]->update(['status' => BudgetTerm::STATUS_PAID]);

        $second = Invoice::create([
            'number' => Invoice::nextNumber(),
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'budget_term_id' => $terms[1]->id,
            'type' => InvoiceType::Termijn,
            'description' => $terms[1]->description,
            'date' => today()->subDays(21),
            'due_date' => today()->subDays(7),
            'amount' => $budget->termAmount($terms[1], $totals),
            'vat_pct' => $totals->vatPercentage,
            'status' => InvoiceStatus::Verzonden,
            'sent_at' => now()->subDays(21),
        ]);

        $second->lines()->create(['description' => $terms[1]->description, 'unit_price' => $second->amount]);
        $terms[1]->update(['status' => BudgetTerm::STATUS_INVOICED]);

        // Een bestelling die geleverd is met factuur, en een die nog onderweg is.
        $delivered = PurchaseOrder::create([
            'number' => PurchaseOrder::nextNumber(),
            'project_id' => $project->id,
            'supplier' => 'Tegelgroothandel Gelderland',
            'category' => 'materiaal',
            'status' => PurchaseOrder::STATUS_DELIVERED,
            'ordered_on' => today()->subDays(18),
            'expected_on' => today()->subDays(12),
            'delivered_on' => today()->subDays(11),
            'chapter_code' => '41',
            'created_by' => $user?->id,
        ]);

        $delivered->lines()->create(['description' => 'Wandtegel 30x60 wit', 'quantity' => 24, 'unit' => 'm2', 'unit_price' => 31.20]);
        $delivered->lines()->create(['description' => 'Vloertegel 60x60 antraciet', 'quantity' => 7, 'unit' => 'm2', 'unit_price' => 46.50]);

        PurchaseInvoice::create([
            'number' => 'TG-2026-4471',
            'supplier' => 'Tegelgroothandel Gelderland',
            'project_id' => $project->id,
            'purchase_order_id' => $delivered->id,
            'category' => 'materiaal',
            'date' => today()->subDays(10),
            'due_date' => today()->addDays(20),
            'amount' => $delivered->total(),
            'status' => PurchaseInvoice::STATUS_APPROVED,
            'approved_at' => now()->subDays(8),
            'approved_by' => $user?->id,
        ]);

        $pending = PurchaseOrder::create([
            'number' => PurchaseOrder::nextNumber(),
            'project_id' => $project->id,
            'supplier' => 'Sanitairhandel Arnhem',
            'category' => 'materiaal',
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'ordered_on' => today()->subDays(6),
            'expected_on' => today()->subDays(1),
            'chapter_code' => '74',
            'created_by' => $user?->id,
        ]);

        $pending->lines()->create(['description' => 'Inloopdouche met glaswand', 'quantity' => 1, 'unit' => 'st', 'unit_price' => 980]);

        // Een inkoopfactuur die nog beoordeeld moet worden.
        PurchaseInvoice::create([
            'number' => 'VH-88213',
            'supplier' => 'Verhuur Materieel Oost',
            'project_id' => $project->id,
            'category' => 'materieel',
            'date' => today()->subDays(3),
            'due_date' => today()->addDays(27),
            'amount' => 215,
            'status' => PurchaseInvoice::STATUS_NEW,
            'notes' => 'Containerhuur twee weken',
        ]);
    }

    /** Een klus die loopt met foto-bewijs, een melding en een werkbon ter controle. */
    private function jobs(Project $project, ?User $person): void
    {
        if ($person === null || JobExecution::whereHas('scheduleEntry', fn ($q) => $q->where('project_id', $project->id))->exists()) {
            return;
        }

        $entry = ScheduleEntry::firstOrCreate([
            'user_id' => $person->id,
            'project_id' => $project->id,
            'title' => 'Tegelwerk badkamer',
        ], [
            'customer_id' => $project->customer_id,
            'type' => ScheduleEntryType::Project,
            'date' => today()->subDay(),
            'end_date' => today()->addDays(2),
        ]);

        $job = JobExecution::forEntry($entry);

        $job->update([
            'status' => JobStatus::Bezig,
            'lmra_confirmed_at' => now()->subHours(6),
            'started_at' => now()->subHours(6),
        ]);

        // De eerste helft van de stappen af, inclusief het foto-bewijs.
        foreach ($job->steps->take(4) as $step) {
            if ($step->photo_required) {
                $step->photos()->create([
                    'project_id' => $project->id,
                    'uploaded_by' => $person->id,
                    'path' => 'project-photos/demo-bewijs.jpg',
                    'caption' => $step->title,
                    'client_visible' => false,
                ]);
            }

            $step->update(['completed_at' => now()->subHours(4), 'completed_by' => $person->id]);
        }

        $job->reports()->create([
            'project_id' => $project->id,
            'reported_by' => $person->id,
            'type' => JobReportType::Materiaal,
            'body' => 'Twee gipsplaten beschadigd geleverd, ik heb er twee extra nodig.',
            'urgent' => false,
            'status' => JobReport::STATUS_OPEN,
        ]);

        // En een werkbon van gisteren die op goedkeuring wacht.
        $yesterday = ScheduleEntry::create([
            'user_id' => $person->id,
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'type' => ScheduleEntryType::Project,
            'title' => 'Sloopwerk badkamer',
            'date' => today()->subDays(4),
            'end_date' => today()->subDays(3),
        ]);

        WorkOrder::create([
            'number' => WorkOrder::nextNumber(),
            'project_id' => $project->id,
            'user_id' => $person->id,
            'schedule_entry_id' => $yesterday->id,
            'title' => 'Sloopwerk badkamer',
            'date' => today()->subDays(4),
            'end_date' => today()->subDays(3),
            'status' => WorkOrderStatus::Controle,
            'hours' => 14,
            'budget_hours' => 16,
            'submitted_at' => now()->subDays(3),
            'signed_name' => 'Mevrouw Jansen',
            'signed_at' => now()->subDays(3),
            'materials' => [['quantity' => 1, 'unit' => 'pst', 'description' => 'container 6 kuub']],
            'notes' => 'Achter de wand zat een oude leiding die niet op tekening stond.',
        ]);
    }

    /** Twee partners, een prijsaanvraag met biedingen. */
    private function partners(Project $project): void
    {
        if (Subcontractor::exists()) {
            return;
        }

        $good = Subcontractor::create([
            'name' => 'Loodgietersbedrijf Van Dijk',
            'contact_name' => 'Marco van Dijk',
            'email' => 'marco@vandijk-loodgieters.test',
            'phone' => '026-1234567',
            'city' => 'Arnhem',
            'coc_number' => '12345678',
            'trades' => ['loodgieterswerk', 'verwarming'],
            'hourly_rate' => 62,
            'region' => 'Gelderland',
            'rating' => 4,
            'id_checked' => true,
        ]);

        foreach (array_keys(CertificateCatalogue::PARTNER_DOCUMENTS) as $type) {
            $good->documents()->create(['type' => $type, 'valid_until' => today()->addYear()]);
        }

        foreach (array_keys(CertificateCatalogue::PARTNER_AGREEMENTS) as $type) {
            $good->documents()->create(['type' => $type, 'signed_at' => now()->subMonths(4)]);
        }

        // En een partij waarvan het dossier nog niet rond is.
        $incomplete = Subcontractor::create([
            'name' => 'Elektra Service Oost',
            'contact_name' => 'Jeroen Bos',
            'email' => 'jeroen@elektraservice.test',
            'city' => 'Zevenaar',
            'trades' => ['elektra'],
            'id_checked' => true,
        ]);

        $incomplete->documents()->create(['type' => 'KvK-uittreksel', 'valid_until' => today()->addMonths(9)]);
        $incomplete->documents()->create(['type' => 'VCA-certificaat', 'valid_until' => today()->subWeeks(2)]);

        $rfq = Rfq::create([
            'number' => Rfq::nextNumber(),
            'project_id' => $project->id,
            'title' => 'Loodgieterswerk badkamer',
            'chapter_code' => '52',
            'scope' => "Waterleiding aanpassen en afvoer verplaatsen.\nExclusief tegelwerk en sanitair plaatsen.",
            'budget' => 1250,
            'status' => Rfq::STATUS_SENT,
            'deadline' => today()->addDays(3),
            'starts_on' => today()->addDays(10),
        ]);

        $rfq->invited()->attach([
            $good->id => ['invited_at' => now()->subDays(4), 'created_at' => now(), 'updated_at' => now()],
            $incomplete->id => ['invited_at' => now()->subDays(4), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $rfq->bids()->create([
            'subcontractor_id' => $good->id,
            'price' => 1180,
            'can_start_on' => today()->addDays(10),
            'days' => 2,
            'conditions' => 'Exclusief doorvoeren door de begane grondvloer',
            'completeness' => 100,
            'received_on' => today()->subDays(2),
        ]);
    }

    /** Kwaliteitscontrole met restpunten en een servicemelding. */
    private function quality(Project $running, Project $delivered, ?User $leader, ?User $worker): void
    {
        $review = QualityReview::forProject($running);

        if ($review->checks->whereNotNull('checked_at')->isEmpty()) {
            foreach ($review->checks->take(3) as $check) {
                $check->update(['checked_at' => now()->subDays(2), 'checked_by' => $leader?->id]);
            }
        }

        if ($running->snags()->doesntExist()) {
            $running->snags()->create([
                'quality_review_id' => $review->id,
                'title' => 'Kitnaad bij de douchehoek niet strak',
                'location' => 'Badkamer, douchehoek rechts',
                'status' => SnagItem::STATUS_OPEN,
                'assigned_to' => $worker?->id,
                'due_on' => today()->subDays(2),
                'source' => 'intern',
            ]);

            $running->snags()->create([
                'quality_review_id' => $review->id,
                'title' => 'Plint ontbreekt bij de deur',
                'location' => 'Hal, bij de badkamerdeur',
                'status' => SnagItem::STATUS_RESOLVED,
                'assigned_to' => $worker?->id,
                'resolved_at' => now()->subDay(),
                'resolved_by' => $worker?->id,
                'source' => 'klant',
            ]);
        }

        if (ServiceTicket::doesntExist()) {
            ServiceTicket::create([
                'number' => ServiceTicket::nextNumber(),
                'project_id' => $delivered->id,
                'customer_id' => $delivered->customer_id,
                'title' => 'Deur van de meterkast klemt',
                'description' => 'Sinds vorige week klemt de deur van de meterkast bij het sluiten.',
                'status' => ServiceTicket::STATUS_NEW,
                'priority' => 'normaal',
                'warranty' => true,
                'warranty_note' => 'Binnen de garantietermijn van 12 maanden.',
                'reported_via' => 'telefoon',
                'reported_by' => $leader?->id,
            ]);
        }
    }

    /** Een agent die klaar is en een voorstel dat op een besluit wacht. */
    private function agents(Project $project, ?User $leader): void
    {
        if (AgentRun::exists()) {
            return;
        }

        $done = AgentRun::create([
            'reference' => AgentRun::nextReference(),
            'agent' => 'finn',
            'skill' => 'budget_forecast',
            'project_id' => $project->id,
            'status' => AgentRun::STATUS_DONE,
            'trigger' => 'dagelijkse controle',
            'started_at' => now()->subHours(9),
            'finished_at' => now()->subHours(9)->addSeconds(14),
            'cost' => 0.0180,
            'steps' => [
                ['at' => now()->subHours(9)->toDateTimeString(), 'description' => 'Begroting, uren en inkoop gelezen', 'data' => null],
                ['at' => now()->subHours(9)->addSeconds(11)->toDateTimeString(), 'description' => 'Prognose bijgewerkt: verwachte marge 2,4 procent onder begroting', 'data' => null],
            ],
            'note' => 'Verwachte marge staat 2,4 procent onder de begroting door extra uren bij het sloopwerk.',
        ]);

        AgentEvent::emit('run.started', 'agent:finn', ['skill' => 'budget_forecast'], $done, $project->id);
        AgentEvent::emit('run.finished', 'agent:finn', ['toelichting' => $done->note], $done, $project->id);

        $waiting = AgentRun::create([
            'reference' => AgentRun::nextReference(),
            'agent' => 'finn',
            'skill' => 'propose_variation',
            'project_id' => $project->id,
            'status' => AgentRun::STATUS_WAITING_APPROVAL,
            'trigger' => 'signaal uit de projectchat',
            'started_at' => now()->subHours(3),
            'cost' => 0.0240,
            'steps' => [
                ['at' => now()->subHours(3)->toDateTimeString(), 'description' => 'Bericht uit de projectchat gelezen: klant vroeg om twee extra stopcontacten', 'data' => null],
                ['at' => now()->subHours(3)->addSeconds(9)->toDateTimeString(), 'description' => 'Prijs opgezocht in de prijslijst en planningsimpact bepaald', 'data' => null],
            ],
        ]);

        $approval = AgentApproval::create([
            'reference' => AgentApproval::nextReference(),
            'agent_run_id' => $waiting->id,
            'project_id' => $project->id,
            'agent' => 'finn',
            'skill' => 'propose_variation',
            'action' => 'Meerwerk twee wandcontactdozen voorstellen',
            'summary' => 'EUR 170 exclusief btw en een halve dag extra werk, volgens de prijslijst.',
            'payload' => ['prijs' => 'EUR 170,00', 'extra werk' => 'een halve dag', 'bron' => 'prijslijst ARB-003 en MAT-014'],
            'fingerprint' => AgentApproval::fingerprint(['prijs' => 170, 'dagen' => 0.5]),
            'status' => AgentApproval::STATUS_OPEN,
            'approver_role' => 'Projectleider en klant',
        ]);

        AgentEvent::emit('approval.requested', 'agent:finn', [
            'actie' => $approval->action,
            'goedkeurder' => $approval->approver_role,
        ], $waiting, $project->id, $approval);
    }

    /** Een intake die halfweg is, zodat de score iets laat zien. */
    private function intake(Project $project): void
    {
        $lead = $project->lead;

        if ($lead === null || Intake::where('lead_id', $lead->id)->exists()) {
            return;
        }

        Intake::create([
            'lead_id' => $lead->id,
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'step' => 3,
            'answers' => [
                'wensen' => 'Complete badkamer vernieuwen, inloopdouche in plaats van bad.',
                'ruimtes' => 'Badkamer eerste verdieping, 6,5 m2.',
                'budget' => 'Rond de 18.000 euro, met wat ruimte.',
                'startWens' => 'Zo snel mogelijk na de kerstvakantie.',
                'situatie' => 'Tegelwerk uit 1998, bad met douchewand, vloer is beton.',
                'afmetingen' => '2,60 bij 2,50 meter, hoogte 2,45.',
                // Bereikbaarheid en aandachtspunten staan bewust nog open.
            ],
        ]);
    }
}
