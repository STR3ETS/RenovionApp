<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ToolOffer;
use App\Models\User;
use App\Support\OfferToolAccess;
use App\Support\OfferToolPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use stdClass;
use Tests\TestCase;

class OfferToolTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '0612345678';

    private const CODE = '482913';

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        // Dinsdagmiddag in Amsterdam, midden in de week dat de code geldig is.
        $this->travelTo(Carbon::parse('2026-11-10 12:00', OfferToolAccess::TIMEZONE));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function person(array $extra = []): array
    {
        $person = [
            'id' => 'p1', 'naam' => 'Hamid Reza', 'rol' => 'klant', 'codeOnly' => false,
            'from' => '2026-11-07T15:49', 'until' => '2026-11-14T15:49', 'open' => true,
            'salt' => 'zout-en-peper',
            ...$extra,
        ];

        $code = $person['codeOnly'] ? 'AB12CD34' : self::CODE;
        $person['codeHash'] = hash('sha256', $person['salt'].'|'.($person['codeOnly'] ? '' : self::PHONE).'|'.$code);

        return $person;
    }

    /**
     * @param  list<array<string, mixed>>  $persons
     * @param  array<string, mixed>  $offerte
     * @param  array<string, mixed>  $access
     */
    private function offer(array $persons = [], array $offerte = [], array $access = []): ToolOffer
    {
        return ToolOffer::create([
            'number' => '23102',
            'rev' => 3,
            'saved_at' => now(),
            'model' => json_encode([
                'v' => 2,
                'meta' => ['project' => 'Nieuwbouw woning kavel 4-15', 'nummer' => '23102', 'opdrachtgever' => 'Dhr. Hamid Reza'],
                'chapters' => [['id' => 'c1', 'code' => '05', 'name' => 'Bouwplaatsvoorzieningen geheim bedrag', 'pars' => []]],
                'attachments' => [],
                'access' => ['on' => true, 'open' => true, 'pdf' => false, 'persons' => $persons, ...$access],
                'offerte' => ['nr' => 'REN-2026-0007', 'versie' => 2, 'status' => 'verzonden', 'specNotes' => new stdClass, ...$offerte],
            ]),
        ]);
    }

    /** Alleen wat de server in de pagina zet: het script direct na <body>. */
    private function injected(string $html): string
    {
        $start = strpos($html, '<body><script>');
        $this->assertNotFalse($start, 'De instelling staat niet direct na <body>.');

        return substr($html, $start, strpos($html, '</script>', $start) - $start);
    }

    private function logIn(ToolOffer $offer, string $phone = self::PHONE, string $code = self::CODE)
    {
        return $this->postJson(route('offer-tool.client.login', $offer), ['tel' => $phone, 'code' => $code]);
    }

    public function test_the_shipped_tool_still_has_the_anchors_the_server_patches(): void
    {
        $tool = OfferToolPage::read(OfferToolPage::TOOL);

        $this->assertSame(1, substr_count($tool, '<body>'));
        foreach (array_keys(OfferToolPage::PER_OFFER) as $anchor) {
            $this->assertSame(1, substr_count($tool, $anchor), "Anker ontbreekt in de tool: {$anchor}");
        }

        $this->assertSame(1, substr_count(OfferToolPage::read(OfferToolPage::LOGIN), '<body>'));
    }

    public function test_the_server_computes_codes_the_same_way_as_the_tool(): void
    {
        // Elk van deze schrijfwijzen is hetzelfde nummer.
        foreach (['06 12 34 56 78', '+31 6 12345678', '0031612345678', '31612345678'] as $phone) {
            $this->assertSame(self::PHONE, OfferToolAccess::phone($phone));
        }

        $this->assertSame('AB12CD', OfferToolAccess::code(' ab-12 cd '));

        $person = (object) $this->person();
        $this->assertSame($person->codeHash, OfferToolAccess::hash($person, '+31 6 1234 5678', self::CODE));
    }

    public function test_team_opens_the_tool_with_its_saved_model(): void
    {
        $admin = User::factory()->create();
        $offer = $this->offer([$this->person()]);

        $this->actingAs($admin)->get(route('offer-tool.index'))
            ->assertOk()
            ->assertSee('23102')
            ->assertSee('Nieuwbouw woning kavel 4-15')
            ->assertSee('1 persoon met een geldige code');

        $response = $this->actingAs($admin)->get(route('offer-tool.show', $offer));

        $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $html = $response->getContent();

        $this->assertStringContainsString('<body><script>window.RENOVION={"mode":"team"', $html);
        $this->assertStringContainsString('"saveUrl":"/offertetool/23102"', $html);
        $this->assertStringContainsString('"rev":3', $html);
        // Het concept in de browser is per offerte gescheiden.
        $this->assertStringContainsString("const KEY='renovion-begroting-v2-23102'", $html);
        $this->assertStringContainsString("indexedDB.open('renovion-begroting-23102',1)", $html);
    }

    public function test_only_roles_that_calculate_or_sell_get_the_tool(): void
    {
        $offer = $this->offer();

        foreach ([UserRole::Uitvoerder, UserRole::Projectleider, UserRole::Inkoper] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('offer-tool.index'))->assertForbidden();
            $this->actingAs($user)->get(route('offer-tool.show', $offer))->assertForbidden();
            $this->actingAs($user)->putJson(route('offer-tool.save', $offer), ['rev' => 3, 'model' => ['chapters' => []]])->assertForbidden();
        }

        $calculator = User::factory()->create(['role' => UserRole::Calculator]);
        $this->actingAs($calculator)->get(route('offer-tool.index'))->assertOk();
    }

    public function test_saving_needs_the_latest_revision(): void
    {
        $admin = User::factory()->create();
        $offer = $this->offer();
        $model = ['chapters' => [], 'meta' => ['project' => 'Aangepast'], 'offerte' => ['specNotes' => new stdClass]];

        // Een collega heeft intussen opgeslagen: deze tool liep achter.
        $this->actingAs($admin)->putJson(route('offer-tool.save', $offer), ['rev' => 2, 'model' => $model])
            ->assertStatus(409)
            ->assertJson(['error' => 'conflict', 'rev' => 3]);

        $this->actingAs($admin)->putJson(route('offer-tool.save', $offer), ['rev' => 3, 'model' => $model])
            ->assertOk()
            ->assertJson(['rev' => 4]);

        $offer->refresh();
        $this->assertSame(4, $offer->rev);
        $this->assertSame($admin->id, $offer->saved_by);
        $this->assertSame('Aangepast', $offer->project());
        // Een leeg object blijft een object, anders raakt de tool zijn notities kwijt.
        $this->assertStringContainsString('"specNotes":{}', $offer->model);

        $this->actingAs($admin)->putJson(route('offer-tool.save', $offer), ['rev' => 4, 'model' => ['geen' => 'begroting']])
            ->assertStatus(422);
    }

    public function test_the_tool_stays_on_one_offer_until_it_is_cleaned(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('offer-tool.store'), ['number' => '23102'])
            ->assertRedirect(route('offer-tool.show', '23102'));

        // De tool bevat nog de gegevens van dit ene project.
        $this->actingAs($admin)->post(route('offer-tool.store'), ['number' => '23103'])->assertForbidden();
        $this->actingAs($admin)->get(route('offer-tool.index'))->assertSee('De tool staat op één offerte.');

        config(['renovion.offer_tool.multi_project' => true]);

        $this->actingAs($admin)->post(route('offer-tool.store'), ['number' => '23103'])
            ->assertRedirect(route('offer-tool.show', '23103'));
        $this->actingAs($admin)->post(route('offer-tool.store'), ['number' => '23103'])
            ->assertSessionHasErrors('number');
        $this->actingAs($admin)->post(route('offer-tool.store'), ['number' => '../etc'])
            ->assertSessionHasErrors('number');
    }

    public function test_without_a_code_the_client_gets_a_login_page_and_nothing_of_the_offer(): void
    {
        $offer = $this->offer([$this->person()]);

        $response = $this->get(route('offer-tool.client', $offer));

        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $html = $response->getContent();
        $this->assertStringContainsString('window.RENOVION_LOGIN={"url":"/inzage/23102/toegang"', $html);
        $this->assertStringNotContainsString('geheim bedrag', $html);
        $this->assertStringNotContainsString('codeHash', $html);
        $this->assertStringNotContainsString('zout-en-peper', $html);
    }

    public function test_the_right_phone_and_code_open_the_offer_without_the_access_list(): void
    {
        $offer = $this->offer([$this->person()]);

        $this->logIn($offer, '+31 6 12 34 56 78')
            ->assertOk()
            ->assertJson(['redirect' => '/inzage/23102']);

        $html = $this->get(route('offer-tool.client', $offer))->assertOk()->getContent();

        $this->assertStringContainsString('window.RENOVION={"mode":"klant"', $html);
        $this->assertStringContainsString('"person":{"id":"p1","naam":"Hamid Reza","rol":"klant"}', $html);
        $this->assertStringContainsString('geheim bedrag', $html);
        // De klant krijgt de personen en hun controlegetallen nooit mee.
        $this->assertStringContainsString('"persons":[]', $html);
        $this->assertStringNotContainsString('codeHash', $this->injected($html));
        $this->assertStringNotContainsString('zout-en-peper', $html);

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'tool_offer', 'action' => 'toegang', 'source' => 'website']);
    }

    public function test_a_share_code_of_eight_characters_works_without_a_phone_number(): void
    {
        $offer = $this->offer([$this->person(['id' => 'p2', 'rol' => 'architect', 'codeOnly' => true])]);

        $this->logIn($offer, '', 'ab12-cd34')->assertOk();
        // Een gewone code van zes cijfers past niet op een deelcode.
        $this->assertNull(OfferToolAccess::find($offer->data(), self::PHONE, self::CODE));
    }

    public function test_wrong_expired_closed_and_early_codes_are_refused(): void
    {
        $offer = $this->offer([$this->person()]);
        $this->logIn($offer, self::PHONE, '000000')->assertStatus(401)->assertJson(['error' => 'onbekend']);
        $this->logIn($offer, '0687654321')->assertStatus(401);
        $offer->delete();

        $expired = $this->offer([$this->person(['until' => '2026-11-09'])]);
        $this->logIn($expired)->assertStatus(403)->assertJson(['error' => 'verlopen', 'until' => '2026-11-09']);
        $expired->delete();

        $early = $this->offer([$this->person(['from' => '2026-11-11T09:00'])]);
        $this->logIn($early)->assertStatus(403)->assertJson(['error' => 'nognniet', 'from' => '2026-11-11T09:00']);
        $early->delete();

        $personClosed = $this->offer([$this->person(['open' => false])]);
        $this->logIn($personClosed)->assertStatus(403)->assertJson(['error' => 'dicht']);
        $personClosed->delete();

        $offerClosed = $this->offer([$this->person()], access: ['open' => false]);
        $this->logIn($offerClosed)->assertStatus(403)->assertJson(['error' => 'dicht']);

        $this->get(route('offer-tool.client', $offerClosed))->assertSee('RENOVION_LOGIN', false);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'tool_offer', 'action' => 'toegang_geweigerd']);
    }

    public function test_a_date_without_time_runs_until_the_end_of_that_day(): void
    {
        $offer = $this->offer([$this->person(['until' => '2026-11-10'])]);

        $this->travelTo(Carbon::parse('2026-11-10 23:58', OfferToolAccess::TIMEZONE));
        $this->logIn($offer)->assertOk();

        $this->travelTo(Carbon::parse('2026-11-11 00:01', OfferToolAccess::TIMEZONE));
        $this->logIn($offer)->assertStatus(403)->assertJson(['error' => 'verlopen']);
    }

    public function test_guessing_is_blocked_after_five_attempts(): void
    {
        $offer = $this->offer([$this->person()]);

        foreach (range(1, 5) as $attempt) {
            $this->logIn($offer, self::PHONE, '11111'.$attempt)->assertStatus(401);
        }

        // Ook de goede code komt er nu niet meer door.
        $this->logIn($offer)->assertStatus(429);

        $this->travel(16)->minutes();
        $this->logIn($offer)->assertOk();
    }

    public function test_closing_or_renewing_access_ends_a_running_session_at_once(): void
    {
        $offer = $this->offer([$this->person()]);
        $this->logIn($offer)->assertOk();
        $this->get(route('offer-tool.client', $offer))->assertSee('"mode":"klant"', false);

        // Het team vernieuwt de code: de hash verandert, de oude sessie vervalt.
        $model = $offer->data();
        $model->access->persons[0]->codeHash = hash('sha256', 'nieuwe-code');
        $offer->putData($model);
        $offer->save();

        $this->get(route('offer-tool.client', $offer))
            ->assertSee('RENOVION_LOGIN', false)
            ->assertDontSee('"mode":"klant"', false);
        $this->postJson(route('offer-tool.client.sign', $offer), [])->assertStatus(401);
    }

    public function test_a_session_ends_when_the_code_expires(): void
    {
        $offer = $this->offer([$this->person()]);
        $this->logIn($offer)->assertOk();

        $this->travelTo(Carbon::parse('2026-11-14 15:50', OfferToolAccess::TIMEZONE));

        $this->get(route('offer-tool.client', $offer))->assertSee('RENOVION_LOGIN', false);
    }

    public function test_the_client_signs_and_the_team_hears_about_it(): void
    {
        $offer = $this->offer([$this->person()]);
        $payload = [
            'persoon' => 'p1', 'naam' => 'Hamid Reza', 'functie' => 'Opdrachtgever', 'plaats' => 'Amsterdam',
            'datum' => '2026-11-10', 'sig' => self::PNG, 'nr' => 'REN-2026-0007', 'versie' => 2,
        ];

        // Zonder inloggen komt er geen akkoord binnen.
        $this->postJson(route('offer-tool.client.sign', $offer), $payload)->assertStatus(401);

        $this->logIn($offer)->assertOk();

        // De klant had een oudere versie voor zich.
        $this->postJson(route('offer-tool.client.sign', $offer), [...$payload, 'versie' => 1])
            ->assertStatus(409)->assertJson(['error' => 'andere_versie']);
        $this->postJson(route('offer-tool.client.sign', $offer), [...$payload, 'sig' => 'data:text/html;base64,PGI+'])
            ->assertStatus(422);

        $this->postJson(route('offer-tool.client.sign', $offer), $payload)->assertOk()->assertJson(['ok' => true]);

        $offer->refresh();
        $this->assertSame('getekend', $offer->status());
        $this->assertSame('2026-11-10', $offer->data()->offerte->getekend);
        $this->assertSame('Hamid Reza', $offer->data()->offerte->getekendDoor);
        $this->assertSame(4, $offer->rev);

        $this->assertDatabaseHas('tool_offer_signatures', [
            'tool_offer_id' => $offer->id, 'name' => 'Hamid Reza', 'person_id' => 'p1',
            'offer_number' => 'REN-2026-0007', 'version' => 2,
        ]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'tool_offer', 'action' => 'getekend', 'source' => 'website']);
        $this->assertDatabaseHas('chat_messages', ['body' => 'Offerte REN-2026-0007 versie 2 is getekend door Hamid Reza.']);

        // Twee keer tekenen kan niet.
        $this->postJson(route('offer-tool.client.sign', $offer), $payload)
            ->assertStatus(409)->assertJson(['error' => 'al_getekend']);
    }

    public function test_an_architect_looks_along_but_cannot_sign(): void
    {
        $offer = $this->offer([$this->person(['rol' => 'architect'])]);
        $this->logIn($offer)->assertOk();

        $this->postJson(route('offer-tool.client.sign', $offer), [
            'naam' => 'Architect', 'sig' => self::PNG, 'nr' => 'REN-2026-0007', 'versie' => 2,
        ])->assertStatus(403);

        $this->assertSame('verzonden', $offer->fresh()->status());
        $this->assertDatabaseCount('tool_offer_signatures', 0);
    }

    public function test_attachments_are_for_the_team_and_for_clients_with_a_valid_code(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $offer = $this->offer([$this->person()]);

        $url = $this->actingAs($admin)
            ->post(route('offer-tool.upload', $offer), [
                'file' => UploadedFile::fake()->create('tekening.pdf', 120, 'application/pdf'),
                'id' => 'i1abcd',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('url');

        $this->assertStringStartsWith('/inzage/23102/bestanden/', $url);

        // Het team dat de tool mag gebruiken, ziet de bijlage.
        $this->actingAs($admin)->get($url)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/octet-stream');

        // Een uitvoerder niet.
        $this->actingAs(User::factory()->uitvoerder()->create())->get($url)->assertForbidden();
    }

    public function test_a_guest_gets_no_attachment_and_a_logged_in_client_does(): void
    {
        Storage::fake('local');
        $offer = $this->offer([$this->person()]);
        $name = '0b9d6f0e-7a55-4d1c-9a57-2f3f5b1f6c11';
        Storage::put('offer-tool/'.$offer->id.'/'.$name, 'inhoud');
        $url = route('offer-tool.file', ['offer' => $offer, 'file' => $name]);

        $this->get($url)->assertForbidden();

        $this->logIn($offer)->assertOk();
        $this->get($url)->assertOk();

        // Een bestandsnaam die geen bijlage van ons is, bestaat niet.
        $this->get('/inzage/23102/bestanden/..%2F..%2F.env')->assertNotFound();
    }

    public function test_text_from_the_model_cannot_break_out_of_the_page(): void
    {
        $offer = $this->offer([$this->person()]);
        $model = $offer->data();
        $model->meta->project = '</script><script>alert(1)</script>';
        $offer->putData($model);
        $offer->save();

        $this->logIn($offer)->assertOk();
        $html = $this->get(route('offer-tool.client', $offer))->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        // De tekst staat er wel, maar met de haakjes als code.
        $this->assertStringContainsString(
            trim(json_encode('</script><script>alert(1)', JSON_HEX_TAG | JSON_UNESCAPED_SLASHES), '"'),
            $this->injected($html),
        );
        // Ook de inlogpagina, waar de projectnaam op staat.
        $this->assertStringNotContainsString('<script>alert(1)', OfferToolPage::login(['project' => $model->meta->project]));
    }
}
