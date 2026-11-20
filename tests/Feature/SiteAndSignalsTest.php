<?php

namespace Tests\Feature;

use App\Automations\ExpiryReminderAutomation;
use App\Automations\InvoiceReminderAutomation;
use App\Enums\AutomationMode;
use App\Enums\CustomerType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\JobStatus;
use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Mail\NoticeMail;
use App\Models\AutomationSetting;
use App\Models\Customer;
use App\Models\ExtraWork;
use App\Models\Invoice;
use App\Models\JobExecution;
use App\Models\Project;
use App\Models\ProjectAgreement;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\ScheduleEntry;
use App\Models\Subcontractor;
use App\Models\SubcontractorFramework;
use App\Models\User;
use App\Services\AttentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Zonder opdracht geen uitvoering, de aanmelding van de bouw, overeenkomsten
 * met onderaannemers en de signalen die daarbij horen.
 */
class SiteAndSignalsTest extends TestCase
{
    use RefreshDatabase;

    private function labels(): array
    {
        return (new AttentionService)->items()->pluck('label')->all();
    }

    private function setMode(string $key, AutomationMode $mode): void
    {
        AutomationSetting::updateOrCreate(['key' => $key], ['mode' => $mode]);
    }

    public function test_without_a_signed_quote_the_work_view_is_blocked(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $admin = User::factory()->create();
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'project_leader_id' => $admin->id]);
        $project->craftsmen()->attach($uitvoerder);

        $quote = Quote::factory()->create([
            'customer_id' => $project->customer_id, 'project_id' => $project->id, 'status' => QuoteStatus::Verstuurd,
        ]);

        $this->assertFalse($project->fresh()->hasOrder());

        $entry = ScheduleEntry::factory()->create(['user_id' => $uitvoerder->id, 'project_id' => $project->id, 'title' => 'Tegelwerk', 'date' => today()]);
        $job = JobExecution::forEntry($entry);

        $this->actingAs($uitvoerder)->get(route('jobs.show', $entry))
            ->assertOk()
            ->assertSee('Dit werk kan nog niet beginnen');

        // Ook langs de knop om lukt het niet: de server houdt het tegen.
        $this->actingAs($uitvoerder)->patch(route('jobs.status', $job), ['status' => 'onderweg'])->assertSessionHas('error');
        $this->assertSame(JobStatus::Gepland, $job->fresh()->status);

        // Het kantoor ziet de blokkade op het dossier.
        $this->actingAs($admin)->get(route('projects.show', $project))->assertSee('Nog geen opdracht');

        // Getekend: de blokkade is weg.
        $quote->update(['status' => QuoteStatus::Akkoord]);
        $this->assertTrue($project->fresh()->hasOrder());
        $this->actingAs($uitvoerder)->patch(route('jobs.status', $job), ['status' => 'onderweg'])->assertSessionHas('success');
    }

    public function test_the_office_can_confirm_an_order_given_outside_the_system(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $uitvoerder = User::factory()->uitvoerder()->create();
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        Quote::factory()->create(['customer_id' => $project->customer_id, 'project_id' => $project->id, 'status' => QuoteStatus::Concept]);

        $this->assertFalse($project->fresh()->hasOrder());

        $this->actingAs($uitvoerder)->post(route('projects.confirm-order', $project))->assertForbidden();
        $this->actingAs($leider)->post(route('projects.confirm-order', $project))->assertSessionHas('success');

        $this->assertTrue($project->fresh()->hasOrder());
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'project', 'action' => 'opdracht bevestigd']);
    }

    public function test_a_project_without_any_quote_in_the_system_counts_as_ordered(): void
    {
        $this->assertTrue(Project::factory()->create(['customer_id' => Customer::factory()])->hasOrder());
    }

    public function test_the_site_registration_tracks_three_steps_and_the_mail_package(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'name' => 'Kantoor Velperweg']);

        $this->actingAs($leider)->get(route('projects.show', [$project, 'tab' => 'aanmelding']))
            ->assertOk()
            ->assertSee('GPI vooraanmelding')
            ->assertSee('0 van 3 gedaan')
            ->assertSee('WKA@renoviongroep.nl');

        $this->actingAs($leider)->patch(route('site-registrations.update', $project), [
            'gpi_registered_on' => today()->toDateString(), 'office_registered_on' => today()->toDateString(),
            'contact_name' => 'Hans Uitvoerder', 'contact_phone' => '06 1111 2222',
        ])->assertSessionHas('success');

        $registration = $project->siteRegistration()->firstOrFail();
        $this->assertSame(2, $registration->doneSteps());
        $this->assertFalse($registration->isComplete());

        $this->actingAs($leider)->patch(route('site-registrations.update', $project), [
            'gpi_registered_on' => today()->toDateString(), 'office_registered_on' => today()->toDateString(), 'site_registered_on' => today()->toDateString(),
        ]);
        $this->actingAs($leider)->post(route('site-registrations.package', $project))->assertSessionHas('success');

        $registration->refresh();
        $this->assertTrue($registration->isComplete());
        $this->assertSame($leider->id, $registration->package_sent_by);

        $this->actingAs($leider)->get(route('projects.show', [$project, 'tab' => 'aanmelding']))
            ->assertSee('3 van 3 gedaan')
            ->assertSee('mailto:WKA@renoviongroep.nl', false);
    }

    public function test_an_agreement_per_subcontractor_is_recorded_once_and_logged(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $partner = Subcontractor::create(['name' => 'Stuc Oost']);
        $rfq = Rfq::create(['number' => Rfq::nextNumber(), 'project_id' => $project->id, 'title' => 'Stucwerk', 'budget' => 4000, 'status' => Rfq::STATUS_AWARDED]);
        $agreement = ProjectAgreement::create(['project_id' => $project->id, 'subcontractor_id' => $partner->id, 'rfq_id' => $rfq->id]);

        // Na drie dagen zonder getekende overeenkomst komt het in de inbox.
        $this->assertNotContains('Overeenkomst nog niet vastgelegd', $this->labels());
        $this->travel(4)->days();
        $this->assertContains('Overeenkomst nog niet vastgelegd', $this->labels());

        $this->actingAs($leider)->get(route('projects.show', [$project, 'tab' => 'aanmelding']))->assertSee('Stuc Oost')->assertSee('Vastleggen');

        $this->actingAs($leider)->post(route('project-agreements.record', $agreement))->assertSessionHas('success');
        $this->actingAs($leider)->post(route('project-agreements.record', $agreement))->assertSessionHas('error');

        $this->assertTrue($agreement->fresh()->isRecorded());
        $this->assertStringStartsWith('Overeenkomst vastgelegd', $partner->logs()->first()->body);
        $this->assertNotContains('Overeenkomst nog niet vastgelegd', $this->labels());
    }

    public function test_a_subcontractor_framework_runs_six_months_and_is_never_extended_by_itself(): void
    {
        $admin = User::factory()->create();
        $partner = Subcontractor::create(['name' => 'Stuc Oost']);

        $this->actingAs($admin)->post(route('subcontractor-frameworks.store', $partner))->assertSessionHas('success');

        $first = $partner->frameworks()->firstOrFail();
        $this->assertTrue($first->starts_on->isToday());
        $this->assertTrue($first->ends_on->isSameDay(today()->addMonths(SubcontractorFramework::MONTHS)->subDay()));

        // Vijf maanden later: binnen 31 dagen van het einde, dus een signaal.
        $this->travelTo($first->ends_on->copy()->subDays(20));
        $this->assertContains('Raamovereenkomst onderaannemer verloopt', $this->labels());

        // De volgende periode sluit aan op de lopende, en dan is het signaal weg.
        $this->actingAs($admin)->post(route('subcontractor-frameworks.store', $partner));
        $second = $partner->frameworks()->orderByDesc('ends_on')->first();
        $this->assertTrue($second->starts_on->isSameDay($first->ends_on->copy()->addDay()));
        $this->assertNotContains('Raamovereenkomst onderaannemer verloopt', $this->labels());

        // Op het dossier van de partij staan de periodes en het logboek.
        $this->actingAs($admin)->get(route('subcontractors.show', $partner))
            ->assertOk()
            ->assertSee('Volgende periode vastleggen')
            ->assertSee('Raamovereenkomst vastgelegd van');
    }

    public function test_an_overdue_invoice_shows_whether_the_customer_wants_a_reminder(): void
    {
        $wel = Customer::factory()->create(['name' => 'Familie Wel']);
        $niet = Customer::factory()->create(['name' => 'Bedrijf Niet', 'type' => CustomerType::Zakelijk, 'reminders' => false]);

        foreach ([$wel, $niet] as $index => $customer) {
            Invoice::create([
                'number' => 'FAC-2026-000'.($index + 1), 'customer_id' => $customer->id,
                'project_id' => Project::factory()->create(['customer_id' => $customer->id])->id,
                'type' => InvoiceType::Termijn, 'description' => 'Termijn', 'date' => today()->subDays(30), 'due_date' => today()->subDays(5),
                'amount' => 1000, 'vat_pct' => 21, 'status' => InvoiceStatus::Verzonden, 'sent_at' => now()->subDays(30),
            ]);
        }

        $items = (new AttentionService)->items()->where('label', 'Factuur te laat');

        $this->assertCount(2, $items);
        $this->assertStringContainsString('herinnering mag', $items->first(fn ($item) => str_contains($item['title'], 'Familie Wel'))['subtitle']);
        $this->assertStringContainsString('bel zelf', $items->first(fn ($item) => str_contains($item['title'], 'Bedrijf Niet'))['subtitle']);
    }

    public function test_a_payment_reminder_is_only_mailed_to_customers_who_have_reminders_on(): void
    {
        Mail::fake();
        $this->setMode('factuur-herinnering', AutomationMode::Automatisch);

        $wel = Customer::factory()->create(['email' => 'wel@voorbeeld.test']);
        $niet = Customer::factory()->create(['email' => 'niet@voorbeeld.test', 'reminders' => false]);

        foreach ([$wel, $niet] as $index => $customer) {
            Invoice::create([
                'number' => 'FAC-2026-001'.$index, 'customer_id' => $customer->id,
                'project_id' => Project::factory()->create(['customer_id' => $customer->id])->id,
                'type' => InvoiceType::Termijn, 'description' => 'Termijn', 'date' => today()->subDays(30), 'due_date' => today()->subDays(3),
                'amount' => 1000, 'vat_pct' => 21, 'status' => InvoiceStatus::Verzonden, 'sent_at' => now()->subDays(30),
            ]);
        }

        $this->assertSame(1, app(InvoiceReminderAutomation::class)->run());

        Mail::assertSent(NoticeMail::class, fn (NoticeMail $mail) => $mail->hasTo('wel@voorbeeld.test'));
        Mail::assertNotSent(NoticeMail::class, fn (NoticeMail $mail) => $mail->hasTo('niet@voorbeeld.test'));

        // Eén herinnering per factuur: een tweede keer draaien doet niets.
        $this->assertSame(0, app(InvoiceReminderAutomation::class)->run());
        Mail::assertSentCount(1);
    }

    public function test_by_default_the_payment_reminder_only_signals(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'klant@voorbeeld.test']);
        Invoice::create([
            'number' => 'FAC-2026-0020', 'customer_id' => $customer->id, 'project_id' => Project::factory()->create(['customer_id' => $customer->id])->id,
            'type' => InvoiceType::Termijn, 'description' => 'Termijn', 'date' => today()->subDays(30), 'due_date' => today()->subDays(3),
            'amount' => 1000, 'vat_pct' => 21, 'status' => InvoiceStatus::Verzonden, 'sent_at' => now()->subDays(30),
        ]);

        app(InvoiceReminderAutomation::class)->run();

        // Een mail aan een klant gaat de deur uit: dat gebeurt pas als de regel op automatisch staat.
        Mail::assertNothingSent();
        $this->assertDatabaseHas('chat_messages', ['user_id' => null]);
    }

    public function test_an_expiring_document_gets_one_reminder_mail_per_expiry_date(): void
    {
        Mail::fake();
        $partner = Subcontractor::create(['name' => 'Stuc Oost', 'contact_name' => 'Rob', 'email' => 'rob@stucoost.test']);
        $document = $partner->documents()->create(['type' => 'vca', 'valid_until' => today()->addDays(20)]);
        // Te ver weg: nog geen herinnering.
        $partner->documents()->create(['type' => 'avb', 'valid_until' => today()->addDays(90)]);

        $this->assertContains('Dossierstuk verloopt', $this->labels());

        $this->assertSame(1, app(ExpiryReminderAutomation::class)->run());
        $this->assertSame(0, app(ExpiryReminderAutomation::class)->run());
        Mail::assertSentCount(1);
        Mail::assertSent(NoticeMail::class, fn (NoticeMail $mail) => $mail->hasTo('rob@stucoost.test') && str_contains($mail->heading, 'VCA'));

        // Vernieuwd: over een jaar komt er voor de nieuwe datum weer een herinnering.
        $document->update(['valid_until' => today()->addYear()]);
        $this->travel(350)->days();
        $this->assertSame(1, app(ExpiryReminderAutomation::class)->run());
        Mail::assertSentCount(2);
    }

    public function test_the_expiry_window_pops_up_for_directors_on_the_dashboard(): void
    {
        $directie = User::factory()->create(['role' => UserRole::Directie]);
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $partner = Subcontractor::create(['name' => 'Stuc Oost']);
        $partner->documents()->create(['type' => 'vca', 'valid_until' => today()->addDays(10)]);

        $this->actingAs($directie)->get(route('dashboard'))->assertOk()->assertSee('Verloopt <span class="text-brand-500">binnenkort</span>', false);
        $this->actingAs($sales)->get(route('dashboard'))->assertOk()->assertDontSee('Verloopt <span class="text-brand-500">binnenkort</span>', false);
    }

    public function test_at_seventy_five_percent_a_thin_margin_and_an_early_purchase_invoice_are_flagged(): void
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'progress' => 80]);
        $partner = Subcontractor::create(['name' => 'Stuc Oost']);
        $rfq = Rfq::create(['number' => Rfq::nextNumber(), 'project_id' => $project->id, 'title' => 'Stucwerk', 'budget' => 4000, 'status' => Rfq::STATUS_AWARDED, 'awarded_to' => $partner->id]);
        $order = PurchaseOrder::create([
            'number' => PurchaseOrder::nextNumber(), 'project_id' => $project->id, 'supplier' => 'Stuc Oost', 'subcontractor_id' => $partner->id,
            'rfq_id' => $rfq->id, 'photo_required' => true, 'status' => PurchaseOrder::STATUS_SENT, 'category' => 'onderaanneming',
        ]);
        // 5.000 verkoop, 4.300 inkoop: veertien procent, onder de zeventien.
        $rfq->bids()->create(['subcontractor_id' => $partner->id, 'price' => 4300, 'sale_price' => 5000, 'purchase_order_id' => $order->id]);

        $this->assertContains('Marge onder de norm', $this->labels());
        $this->assertNotContains('Inkoopfactuur voor verkoopfactuur', $this->labels());

        PurchaseInvoice::create([
            'number' => 'F-1', 'supplier' => 'Stuc Oost', 'project_id' => $project->id, 'purchase_order_id' => $order->id,
            'category' => 'onderaanneming', 'date' => today(), 'due_date' => today()->addDays(30), 'amount' => 4300, 'vat_pct' => 21,
            'status' => PurchaseInvoice::STATUS_NEW,
        ]);

        // De onderaannemer factureert, wij nog niet.
        $this->assertContains('Inkoopfactuur voor verkoopfactuur', $this->labels());

        // Onder de 75 procent kijken we er nog niet naar.
        $project->update(['progress' => 50]);
        $this->assertNotContains('Marge onder de norm', $this->labels());
    }

    public function test_extra_work_ready_to_invoice_or_waiting_too_long_reaches_the_inbox(): void
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $extra = $project->extraWorks()->create([
            'number' => ExtraWork::nextNumberFor($project), 'description' => 'Extra werk', 'amount' => 500,
            'status' => ExtraWork::STATUS_SENT, 'public_token' => ExtraWork::newToken(), 'sent_at' => now(),
        ]);

        $this->assertNotContains('Meerwerk wacht op akkoord', $this->labels());

        $this->travel(4)->days();
        $this->assertContains('Meerwerk wacht op akkoord', $this->labels());

        $extra->update(['status' => ExtraWork::STATUS_APPROVED, 'approved_at' => now(), 'approved_name' => 'Klant']);
        $this->assertContains('Meerwerk factureren', $this->labels());
    }
}
