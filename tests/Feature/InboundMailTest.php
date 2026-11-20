<?php

namespace Tests\Feature;

use App\Enums\CustomerType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\InboundMail;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\InboundMailReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * De aanvraagmail en de persoonlijke opdrachtlink van zakelijke klanten.
 */
class InboundMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Zonder sleutel leest Nova met vaste patronen: dat houdt de test voorspelbaar.
        config(['renovion.ai.api_key' => null, 'renovion.inbound_mail_token' => 'geheim-token']);
        $this->travelTo(Carbon::parse('2026-11-18 09:00'));
    }

    private function unica(): Customer
    {
        return Customer::factory()->create([
            'name' => 'Unica Installatie', 'type' => CustomerType::Zakelijk, 'email' => 'inkoop@unica.test', 'payment_days' => 30,
        ]);
    }

    public function test_nova_reads_work_address_and_deadline_from_a_mail(): void
    {
        $mail = InboundMail::create([
            'from_email' => 'iemand@gmail.com', 'subject' => 'RE: Stucwerk hal en trapgat',
            'body' => "Beste,\n\nGraag een prijs voor het stucen van de hal aan de Velperweg 12a, 6824 BH Arnhem.\nHet moet uiterlijk 12 december klaar zijn.\n\nGroet, Rob",
        ]);

        $read = app(InboundMailReader::class)->read($mail);

        $this->assertSame('Stucwerk hal en trapgat', $read['werk']);
        $this->assertSame('Velperweg 12a, 6824 BH Arnhem', $read['adres']);
        $this->assertSame('2026-12-12', $read['deadline']);
        $this->assertNull($read['klant_id']);
        $this->assertSame('patronen', $read['bron']);
    }

    public function test_a_date_without_a_year_is_the_next_time_that_date_comes(): void
    {
        $mail = InboundMail::create(['subject' => 'Werk', 'body' => 'Deadline: 05-01. Adres volgt.']);

        $this->assertSame('2027-01-05', app(InboundMailReader::class)->read($mail)['deadline']);
    }

    public function test_the_customer_is_recognised_by_number_address_or_company_domain(): void
    {
        $unica = $this->unica();
        $reader = app(InboundMailReader::class);

        $byEmail = InboundMail::create(['from_email' => 'Inkoop@Unica.test', 'body' => 'Graag een prijs.']);
        $this->assertSame($unica->id, $reader->read($byEmail)['klant_id']);

        // Een collega van hetzelfde bedrijf.
        $byDomain = InboundMail::create(['from_email' => 'projectleider@unica.test', 'body' => 'Graag een prijs.']);
        $this->assertSame($unica->id, $reader->read($byDomain)['klant_id']);

        $byNumber = InboundMail::create(['from_email' => 'onbekend@voorbeeld.test', 'body' => "Klantnummer {$unica->number}, graag een prijs."]);
        $this->assertSame($unica->id, $reader->read($byNumber)['klant_id']);

        // Een gratis mailadres zegt niets over het bedrijf.
        Customer::factory()->create(['type' => CustomerType::Zakelijk, 'email' => 'bedrijf@gmail.com']);
        $public = InboundMail::create(['from_email' => 'iemand.anders@gmail.com', 'body' => 'Graag een prijs.']);
        $this->assertNull($reader->read($public)['klant_id']);
    }

    public function test_a_mail_from_an_existing_customer_goes_straight_to_calculation_with_a_task(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $calculator = User::factory()->create(['role' => UserRole::Calculator, 'name' => 'Rob Calculator']);
        $unica = $this->unica();

        $this->actingAs($sales)->post(route('inbound-mails.store'), [
            'from_name' => 'Inkoop Unica', 'from_email' => 'inkoop@unica.test',
            'subject' => 'Stucwerk kantoor', 'body' => 'Graag een prijs voor het stucen van het kantoor aan de Velperweg 12, 6824 BH Arnhem.',
        ])->assertRedirect();

        $mail = InboundMail::firstOrFail();
        $this->assertSame(InboundMail::STATUS_NEW, $mail->status);
        $this->assertSame($unica->id, $mail->extracted['klant_id']);

        $this->actingAs($sales)->get(route('inbound-mails.index'))
            ->assertOk()
            ->assertSee('Stucwerk kantoor')
            ->assertSee('het mailadres is van deze klant');

        // Uitzetten kan pas na koppelen: een mens bevestigt eerst.
        $this->actingAs($sales)->post(route('inbound-mails.dispatch', $mail))->assertSessionHas('error');
        $this->assertDatabaseCount('leads', 0);

        $this->actingAs($sales)->post(route('inbound-mails.link', $mail), [
            'customer_id' => $unica->id, 'werk' => 'Stucwerk kantoor', 'adres' => 'Velperweg 12, 6824 BH Arnhem', 'deadline' => '2026-12-12',
        ])->assertSessionHas('success');

        $this->actingAs($sales)->post(route('inbound-mails.dispatch', $mail))->assertRedirect();

        $lead = Lead::firstOrFail();
        // Bestaande klant: de aanvraagfase wordt overgeslagen.
        $this->assertSame(LeadStatus::Offerte, $lead->status);
        $this->assertSame($calculator->id, $lead->assigned_to);
        $this->assertSame('Stucwerk kantoor', $lead->service);

        $task = Task::firstOrFail();
        $this->assertSame('Offerte maken: Stucwerk kantoor', $task->title);
        $this->assertSame($calculator->id, $task->owner_id);

        $mail->refresh();
        $this->assertSame(InboundMail::STATUS_DISPATCHED, $mail->status);
        $this->assertSame($lead->id, $mail->lead_id);

        // Niet twee keer uitzetten.
        $this->actingAs($sales)->post(route('inbound-mails.dispatch', $mail))->assertSessionHas('error');
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_a_mail_from_a_new_customer_starts_at_the_request_stage(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $mail = InboundMail::create([
            'from_name' => 'Familie De Vries', 'from_email' => 'devries@voorbeeld.test', 'subject' => 'Badkamer', 'body' => 'Wij willen graag een nieuwe badkamer.',
        ]);

        // Zonder klant of naam valt er niets te koppelen.
        $this->actingAs($sales)->post(route('inbound-mails.link', $mail), ['werk' => 'Badkamer'])->assertSessionHasErrors('customer_id');

        $this->actingAs($sales)->post(route('inbound-mails.link', $mail), [
            'new_name' => 'Familie De Vries', 'new_type' => 'particulier', 'werk' => 'Nieuwe badkamer',
        ])->assertSessionHas('success');

        $customer = Customer::firstWhere('name', 'Familie De Vries');
        $this->assertSame('devries@voorbeeld.test', $customer->email);
        $this->assertSame(14, $customer->payment_days);

        $this->actingAs($sales)->post(route('inbound-mails.dispatch', $mail))->assertRedirect();

        $lead = Lead::firstOrFail();
        $this->assertSame(LeadStatus::Nieuw, $lead->status);
        $this->assertSame($sales->id, $lead->assigned_to);
        // Een nieuwe klant krijgt geen taak Offerte maken: eerst kennismaken.
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_the_mail_provider_delivers_through_the_webhook_with_a_token(): void
    {
        $unica = $this->unica();
        $payload = ['From' => 'Inkoop Unica <inkoop@unica.test>', 'Subject' => 'Stucwerk', 'TextBody' => 'Graag een prijs.', 'MessageID' => 'abc-123'];

        $this->postJson('/api/inkomende-mail', $payload)->assertUnauthorized();
        $this->postJson('/api/inkomende-mail', $payload, ['X-Inbound-Token' => 'fout'])->assertUnauthorized();

        $this->postJson('/api/inkomende-mail', $payload, ['X-Inbound-Token' => 'geheim-token'])->assertCreated();

        $mail = InboundMail::firstOrFail();
        $this->assertSame('inkoop@unica.test', $mail->from_email);
        $this->assertSame('Inkoop Unica', $mail->from_name);
        $this->assertSame($unica->id, $mail->extracted['klant_id']);

        // Hetzelfde bericht nog eens afleveren geeft geen tweede bericht.
        $this->postJson('/api/inkomende-mail', $payload, ['X-Inbound-Token' => 'geheim-token'])
            ->assertOk()->assertJson(['duplicate' => true]);
        $this->assertDatabaseCount('inbound_mails', 1);

        // Zonder ingesteld token staat de ingang dicht.
        config(['renovion.inbound_mail_token' => null]);
        $this->postJson('/api/inkomende-mail', $payload, ['X-Inbound-Token' => ''])->assertUnauthorized();
    }

    public function test_a_business_customer_submits_an_order_through_the_personal_link(): void
    {
        $admin = User::factory()->create();
        $unica = $this->unica();

        // De link bestaat pas als iemand hem aanmaakt, en alleen voor zakelijke klanten.
        $private = Customer::factory()->create();
        $this->actingAs($admin)->post(route('customers.portal-link', $private))->assertSessionHas('error');
        $this->assertNull($private->fresh()->portal_token);

        $this->actingAs($admin)->post(route('customers.portal-link', $unica))->assertSessionHas('success');
        $token = $unica->fresh()->portal_token;
        $this->assertNotNull($token);
        $this->actingAs($admin)->get(route('customers.show', $unica))->assertSee(route('customer-orders.create', $token));
        $this->post('/logout');

        $this->get(route('customer-orders.create', $token))
            ->assertOk()
            ->assertSee('Unica Installatie')
            ->assertSee($unica->number);
        $this->get(route('customer-orders.create', 'bestaat-niet'))->assertNotFound();

        $this->post(route('customer-orders.store', $token), [
            'contact' => 'Rob de Inkoper', 'address' => 'Velperweg 12, 6824 BH Arnhem',
            'work' => 'Wanden en plafond van de hal sausklaar stucen, ongeveer 60 m2', 'deadline' => '2026-12-12',
        ])->assertRedirect()->assertSessionHas('success');

        $mail = InboundMail::firstOrFail();
        // Via de link weten we wie het is: het bericht komt herkend en gekoppeld binnen.
        $this->assertSame(InboundMail::SOURCE_PORTAL, $mail->source);
        $this->assertSame(InboundMail::STATUS_LINKED, $mail->status);
        $this->assertSame($unica->id, $mail->customer_id);
        $this->assertSame('Velperweg 12, 6824 BH Arnhem', $mail->read('adres'));
        $this->assertTrue($mail->isForExistingCustomer());

        // Een robot die het verborgen veld invult, komt er niet door.
        $this->post(route('customer-orders.store', $token), [
            'contact' => 'Bot', 'address' => 'X', 'work' => 'Y', 'website' => 'http://spam.test',
        ])->assertSessionHasErrors('website');

        // Vernieuwen maakt de oude link ongeldig.
        $this->actingAs($admin)->post(route('customers.portal-link', $unica), ['vernieuwen' => 1]);
        $this->post('/logout');
        $this->get(route('customer-orders.create', $token))->assertNotFound();
    }

    public function test_only_sales_and_calculation_roles_work_the_inbound_mail(): void
    {
        $mail = InboundMail::create(['subject' => 'Werk', 'body' => 'Graag een prijs.']);

        foreach ([UserRole::Uitvoerder, UserRole::Projectleider, UserRole::Administratie] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('inbound-mails.index'))->assertForbidden();
            $this->actingAs($user)->post(route('inbound-mails.dispatch', $mail))->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => UserRole::Calculator]))->get(route('inbound-mails.index'))->assertOk();
    }
}
