<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\AccountInvitation;
use App\Models\OsFile;
use App\Models\OsRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * De gedeelde staat van Renovion Groep: inloggen, ophalen, opslaan, bijwerken
 * en de rechten die de server afdwingt.
 */
class OsStateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['name' => 'Imad', 'email' => 'imad@renovion.nl', 'role' => UserRole::Admin]);
    }

    /** @param  list<array{0: string, 1: ?string}>  $changes */
    private function sync(User $user, array $changes, int $since = 0)
    {
        return $this->actingAs($user)->postJson('/os/opslaan', ['since' => $since, 'changes' => $changes]);
    }

    private function row(string $path): mixed
    {
        $value = OsRow::query()->where('path', $path)->value('value');

        return $value === null ? null : json_decode($value, true);
    }

    public function test_gasten_gaan_naar_de_inlogpagina(): void
    {
        $this->get('/')->assertRedirect('/inloggen');
        $this->getJson('/os/staat')->assertUnauthorized();
    }

    public function test_inloggen_en_een_gedeactiveerd_account_komt_er_niet_in(): void
    {
        $this->admin();
        User::factory()->create(['email' => 'weg@renovion.nl', 'active' => false]);

        $this->post('/inloggen', ['email' => 'imad@renovion.nl', 'password' => 'password'])->assertRedirect('/');
        $this->post('/uitloggen');
        $this->post('/inloggen', ['email' => 'weg@renovion.nl', 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_de_schil_laadt_met_de_ingelogde_gebruiker(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->get('/')->assertOk()->assertSee('OS_BOOT', false)->assertSee('"rol":"Superadmin"', false);
    }

    public function test_een_leeg_systeem_start_met_de_fabrieksstaat_en_de_accounts_als_medewerkers(): void
    {
        $user = $this->admin();
        User::factory()->uitvoerder()->create(['name' => 'Peter Mulder']);

        $state = $this->actingAs($user)->getJson('/os/staat')->assertOk()->json();

        $paths = array_column($state['rows'], 0);
        $this->assertContains('emps/imad', $paths);
        $this->assertContains('emps/peter', $paths);
        $this->assertContains('projects/#order', $paths);
        $this->assertNotContains('projects/PD-2026-0041', $paths, 'geen demodata');
        $this->assertSame('imad', $user->fresh()->person_id);
        $this->assertSame([], $state['skeleton']['projects']);
        $this->assertSame('Uitvoerder', $this->row('emps/peter')['sysrol']);
    }

    public function test_opslaan_en_een_collega_ziet_het_bij_de_volgende_ronde(): void
    {
        $imad = $this->admin();
        $raphael = User::factory()->create(['name' => 'Raphael', 'role' => UserRole::Directie]);
        $this->actingAs($imad)->getJson('/os/staat');
        $rev = $this->actingAs($raphael)->getJson('/os/staat')->json('rev');

        $project = ['id' => 'PD-2026-0001', 'naam' => 'Badkamer de Boer', 'clientId' => 'c1'];
        $this->sync($imad, [['projects/PD-2026-0001', json_encode($project)], ['projects/#order', '["PD-2026-0001"]']])
            ->assertOk()->assertJsonPath('rejected', []);

        $pulled = $this->actingAs($raphael)->getJson('/os/wijzigingen?since='.$rev)->assertOk()->json();
        $this->assertContains(['projects/PD-2026-0001', json_encode($project)], $pulled['rows']);
        $this->assertGreaterThan($rev, $pulled['rev']);

        // verwijderen komt als null bij de collega aan
        $this->sync($imad, [['projects/PD-2026-0001', null]]);
        $after = $this->actingAs($raphael)->getJson('/os/wijzigingen?since='.$pulled['rev'])->json('rows');
        $this->assertContains(['projects/PD-2026-0001', null], $after);
    }

    public function test_onbekende_paden_en_andermans_gebruikersregels_worden_geweigerd(): void
    {
        $user = $this->admin();
        $other = User::factory()->create();

        $res = $this->sync($user, [['../geheim', '{}'], ['~'.$other->id.'/academy', '{}'], ['projects', '[]'], ['~me/academy', '{"done":{}}']])->json();

        $this->assertEqualsCanonicalizing(['../geheim', '~'.$other->id.'/academy', 'projects'], $res['rejected']);
        $this->assertSame(['done' => []], $this->row('~'.$user->id.'/academy'));
    }

    public function test_een_nieuwe_medewerker_met_mailadres_krijgt_een_account_met_eenmalig_wachtwoord(): void
    {
        $user = $this->admin();
        $person = ['id' => 'u1', 'naam' => 'Sanne de Wit', 'sysrol' => 'Calculator', 'mail' => 'sanne@renovion.nl', 'status' => 'uitgenodigd'];

        $res = $this->sync($user, [['extraUsers/u1', json_encode($person)]])->assertOk()->json();

        $this->assertCount(1, $res['accounts']);
        $this->assertSame('sanne@renovion.nl', $res['accounts'][0]['mail']);
        $account = User::query()->where('email', 'sanne@renovion.nl')->first();
        $this->assertSame(UserRole::Calculator, $account->role);
        $this->assertSame('u1', $account->person_id);
        $this->assertTrue(password_verify($res['accounts'][0]['wachtwoord'], $account->password));
        $this->assertTrue($account->must_change_password, 'het wachtwoord is tijdelijk');

        // deactiveren sluit het account
        $person['status'] = 'gedeactiveerd';
        $this->sync($user, [['extraUsers/u1', json_encode($person)]]);
        $this->assertFalse($account->fresh()->active);
    }

    public function test_met_een_tijdelijk_wachtwoord_kies_je_eerst_een_eigen_wachtwoord(): void
    {
        $admin = $this->admin();
        $peter = User::factory()->uitvoerder()->create(['name' => 'Peter Mulder', 'email' => 'peter@renovion.nl', 'person_id' => 'peter']);

        $temp = $this->actingAs($admin)->postJson('/os/accounts/peter/wachtwoord')->assertOk()->json('wachtwoord');
        $this->assertTrue($peter->fresh()->must_change_password);
        $this->post('/uitloggen');

        // Inloggen met het tijdelijke wachtwoord: eerst een eigen wachtwoord kiezen
        $this->post('/inloggen', ['email' => 'peter@renovion.nl', 'password' => $temp])->assertRedirect('/');
        $this->get('/projecten')->assertRedirect('/wachtwoord-kiezen');
        $this->getJson('/os/staat')->assertStatus(409);
        $this->get('/wachtwoord-kiezen')->assertOk()->assertSee('Kies je eigen wachtwoord');

        $this->post('/wachtwoord-kiezen', ['password' => 'kort1', 'password_confirmation' => 'kort1'])->assertSessionHasErrors('password');
        $this->post('/wachtwoord-kiezen', ['password' => $temp, 'password_confirmation' => $temp])->assertSessionHasErrors('password');
        $this->post('/wachtwoord-kiezen', ['password' => 'Stucwerk2026!', 'password_confirmation' => 'Stucwerk2026!'])->assertRedirect('/');

        $this->assertFalse($peter->fresh()->must_change_password);
        $this->assertTrue(password_verify('Stucwerk2026!', $peter->fresh()->password));
        $this->get('/projecten')->assertOk();
        $this->get('/wachtwoord-kiezen')->assertRedirect('/');
    }

    public function test_een_nieuw_account_krijgt_een_uitnodiging_per_mail_met_een_inloglink(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $res = $this->sync($admin, [
            ['projects/PD-1', json_encode(['id' => 'PD-1', 'naam' => 'Badkamer Jansen'])],
            ['extraUsers/k1', json_encode(['id' => 'k1', 'naam' => 'Anna Jansen', 'sysrol' => 'Klant', 'mail' => 'anna@example.nl', 'status' => 'uitgenodigd', 'scope' => 'PD-1'])],
        ])->assertOk()->json();

        $this->assertTrue($res['accounts'][0]['gemaild']);
        $link = $res['accounts'][0]['link'];
        $this->assertStringContainsString('/uitnodiging/', $link);
        Mail::assertSent(AccountInvitation::class, fn (AccountInvitation $m) => $m->hasTo('anna@example.nl')
            && $m->kind === 'klant' && $m->project === 'Badkamer Jansen' && $m->link === $link && ! $m->again);

        $klant = User::query()->where('email', 'anna@example.nl')->firstOrFail();
        $mail = new AccountInvitation($klant, $link, 'klant', project: 'Badkamer Jansen');
        $mail->assertSeeInHtml('Beste Anna Jansen');
        $mail->assertSeeInHtml('Badkamer Jansen');
        $mail->assertSeeInText($link);
        $this->assertSame('Renovion Groep BV', $mail->envelope()->from->name);

        // Een link waar aan gezeten is, doet niets
        $this->post('/uitloggen');
        $this->get($link.'x')->assertRedirect('/inloggen')->assertSessionHasErrors('email');
        $this->assertGuest();

        // De link logt in en brengt je naar het kiezen van een eigen wachtwoord
        $this->get($link)->assertRedirect('/wachtwoord-kiezen');
        $this->assertAuthenticatedAs($klant);
        $this->get('/wachtwoord-kiezen')->assertOk()->assertSee('welkom bij Renovion Groep BV');
        $this->post('/wachtwoord-kiezen', ['password' => 'Badkamer2026!', 'password_confirmation' => 'Badkamer2026!'])->assertRedirect('/');

        // Daarna is de link verbruikt
        $this->post('/uitloggen');
        $this->get($link)->assertRedirect('/inloggen')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_een_nieuw_wachtwoord_mailt_een_nieuwe_link_en_de_oude_werkt_niet_meer(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $oud = $this->sync($admin, [['extraUsers/u1', json_encode(['id' => 'u1', 'naam' => 'Sanne de Wit', 'sysrol' => 'Calculator', 'mail' => 'sanne@renovion.nl', 'status' => 'uitgenodigd'])]])->json('accounts.0.link');
        Mail::assertSent(AccountInvitation::class, fn (AccountInvitation $m) => $m->kind === 'team' && $m->by === 'Imad');

        $nieuw = $this->actingAs($admin)->postJson('/os/accounts/u1/wachtwoord')->assertOk()->assertJsonPath('gemaild', true)->json('link');
        Mail::assertSent(AccountInvitation::class, fn (AccountInvitation $m) => $m->again && $m->link === $nieuw);
        $this->assertNotSame($oud, $nieuw);

        $this->post('/uitloggen');
        $this->get($oud)->assertRedirect('/inloggen');
        $this->assertGuest();
        $this->get($nieuw)->assertRedirect('/wachtwoord-kiezen');

        // Een gesloten account komt er ook met de link niet in
        $this->post('/uitloggen');
        User::query()->where('email', 'sanne@renovion.nl')->update(['active' => false]);
        $this->get($nieuw)->assertRedirect('/inloggen');
        $this->assertGuest();
    }

    public function test_zonder_mailserver_gaat_er_geen_mail_uit_en_komt_de_link_alleen_in_beeld(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $admin = $this->admin();

        $account = $this->sync($admin, [['extraUsers/u1', json_encode(['id' => 'u1', 'naam' => 'Sanne de Wit', 'sysrol' => 'Calculator', 'mail' => 'sanne@renovion.nl', 'status' => 'uitgenodigd'])]])->json('accounts.0');

        $this->assertFalse($account['gemaild']);
        $this->assertStringContainsString('/uitnodiging/', $account['link']);
        Mail::assertNothingSent();
        $this->actingAs($admin)->get('/')->assertSee('"mail":false', false);
    }

    public function test_het_commando_zet_tijdelijke_wachtwoorden(): void
    {
        $this->admin();

        $this->artisan('os:tijdelijk-wachtwoord', ['email' => ['IMAD@renovion.nl', 'niemand@renovion.nl']])
            ->expectsOutputToContain('Geen account met niemand@renovion.nl')
            ->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'imad@renovion.nl')->value('must_change_password'));
    }

    public function test_alleen_de_directie_verandert_rollen(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/os/staat');
        $peter = User::factory()->uitvoerder()->create(['name' => 'Peter', 'person_id' => 'peter']);
        $this->sync($admin, [['emps/peter', json_encode(['id' => 'peter', 'naam' => 'Peter', 'sysrol' => 'Uitvoerder', 'mail' => $peter->email, 'status' => 'actief'])]]);

        $this->sync($peter, [['emps/peter', json_encode(['id' => 'peter', 'naam' => 'Peter M', 'sysrol' => 'Superadmin', 'mail' => $peter->email, 'status' => 'actief'])]]);

        $this->assertSame('Uitvoerder', $this->row('emps/peter')['sysrol']);
        $this->assertSame('Peter M', $this->row('emps/peter')['naam'], 'gewone velden mag hij wel aanpassen');
        $this->assertSame(UserRole::Uitvoerder, $peter->fresh()->role);
        $this->actingAs($peter)->postJson('/os/accounts/imad/wachtwoord')->assertForbidden();
    }

    public function test_de_directie_verwijdert_een_gedeactiveerd_account_helemaal_maar_niet_zichzelf(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/os/staat');
        $me = $admin->fresh()->person_id;
        $peter = User::factory()->uitvoerder()->create(['name' => 'Peter', 'email' => 'peter@renovion.nl', 'person_id' => 'peter']);
        $this->sync($admin, [['emps/peter', json_encode(['id' => 'peter', 'naam' => 'Peter', 'sysrol' => 'Uitvoerder', 'mail' => 'peter@renovion.nl', 'status' => 'gedeactiveerd'])]]);
        $this->assertFalse($peter->fresh()->active);
        $this->sync($peter->fresh(), [['~me/academy', json_encode(['gezien' => 1])]]);

        // een collega zonder directierol haalt niemand weg
        $klaas = User::factory()->uitvoerder()->create(['name' => 'Klaas', 'person_id' => 'klaas']);
        $this->sync($klaas, [['emps/peter', null]]);
        $this->assertNotNull($this->row('emps/peter'));
        $this->assertNotNull($peter->fresh());

        // de directie wel: persoon, account en eigen regels zijn weg
        $this->sync($admin, [['emps/peter', null]])->assertOk();
        $this->assertNull($this->row('emps/peter'));
        $this->assertNull(User::query()->find($peter->id), 'het account is echt verwijderd');
        $this->assertSame(0, OsRow::query()->where('path', 'like', '~'.$peter->id.'/%')->count());
        $this->post('/uitloggen');
        $this->post('/inloggen', ['email' => 'peter@renovion.nl', 'password' => 'password'])->assertSessionHasErrors('email');

        // jezelf verwijderen kan niet
        $this->assertNotNull($me);
        $this->sync($admin->fresh(), [['emps/'.$me, null]]);
        $this->assertNotNull($this->row('emps/'.$me));
        $this->assertNotNull($admin->fresh());
    }

    public function test_een_besloten_gesprek_in_de_teamchat_is_alleen_voor_de_deelnemers(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/os/staat');
        $me = $admin->fresh()->person_id;
        $peter = User::factory()->uitvoerder()->create(['name' => 'Peter', 'person_id' => 'peter']);
        $klaas = User::factory()->uitvoerder()->create(['name' => 'Klaas', 'person_id' => 'klaas']);
        $paths = fn (User $u) => collect($this->actingAs($u)->getJson('/os/staat')->assertOk()->json('rows'))->pluck(0)->all();

        $this->sync($admin->fresh(), [
            ['chat.channels/dm1', json_encode(['id' => 'dm1', 'naam' => '', 'type' => 'direct', 'leden' => [$me, 'peter'], 'oms' => ''])],
            ['chat.msgs/m1', json_encode(['id' => 'm1', 'ch' => 'dm1', 'van' => $me, 'txt' => 'Alleen voor Peter', 'd' => '2026-11-27', 'tijd' => '09:00'])],
            ['chat.msgs/m2', json_encode(['id' => 'm2', 'ch' => 'algemeen', 'van' => $me, 'txt' => 'Voor iedereen', 'd' => '2026-11-27', 'tijd' => '09:01'])],
        ])->assertOk();

        // de deelnemers krijgen het gesprek, een collega die er niet in zit niet
        $this->assertContains('chat.channels/dm1', $paths($peter));
        $this->assertContains('chat.msgs/m1', $paths($peter));
        $this->assertNotContains('chat.channels/dm1', $paths($klaas));
        $this->assertNotContains('chat.msgs/m1', $paths($klaas));
        $this->assertContains('chat.msgs/m2', $paths($klaas), 'open kanalen blijven voor iedereen');
        $later = collect($this->actingAs($klaas)->getJson('/os/wijzigingen?since=0')->assertOk()->json('rows'))->pluck(0)->all();
        $this->assertNotContains('chat.msgs/m1', $later);

        // en hij kan zich er niet in schrijven of zichzelf toevoegen
        $res = $this->sync($klaas, [
            ['chat.msgs/m3', json_encode(['id' => 'm3', 'ch' => 'dm1', 'van' => 'klaas', 'txt' => 'Ik lees mee', 'd' => '2026-11-27', 'tijd' => '09:05'])],
            ['chat.channels/dm1', json_encode(['id' => 'dm1', 'naam' => '', 'type' => 'direct', 'leden' => [$me, 'peter', 'klaas']])],
        ])->assertOk()->json('rejected');
        $this->assertEqualsCanonicalizing(['chat.msgs/m3', 'chat.channels/dm1'], $res);
        $this->assertNull($this->row('chat.msgs/m3'));
        $this->assertSame([$me, 'peter'], $this->row('chat.channels/dm1')['leden']);

        // Peter schrijft wel
        $this->sync($peter, [['chat.msgs/m4', json_encode(['id' => 'm4', 'ch' => 'dm1', 'van' => 'peter', 'txt' => 'Gezien', 'd' => '2026-11-27', 'tijd' => '09:06'])]])->assertOk();
        $this->assertSame('Gezien', $this->row('chat.msgs/m4')['txt']);
    }

    public function test_een_klant_ziet_alleen_zijn_eigen_project_en_kan_niets_anders_wijzigen(): void
    {
        $admin = $this->admin();
        $this->sync($admin, [
            ['clients/c1', json_encode(['id' => 'c1', 'naam' => 'Familie de Boer'])],
            ['clients/c2', json_encode(['id' => 'c2', 'naam' => 'Bakkerij Jansen'])],
            ['projects/PD-1', json_encode(['id' => 'PD-1', 'clientId' => 'c1', 'naam' => 'Badkamer', 'begroting' => ['notities' => ['prive' => 'marge 30%', 'vragen' => []], 'options' => [['d' => 'Vloerverwarming', 's' => 'optie']]], 'offerte' => ['status' => 'verzonden', 'basis' => 10000], 'activity' => [], 'planVoorstel' => ['status' => 'open', 'regels' => [['id' => 'pv1', 'titel' => 'Tegelwerk', 'emp' => 'peter', 'start' => '2026-12-07', 'end' => '2026-12-08']]]])],
            ['projects/PD-2', json_encode(['id' => 'PD-2', 'clientId' => 'c2', 'naam' => 'Winkel'])],
            ['projects/#order', '["PD-1","PD-2"]'],
            ['invoices/F-1', json_encode(['nr' => 'F-1', 'pid' => 'PD-1', 'status' => 'verzonden'])],
            ['invoices/F-2', json_encode(['nr' => 'F-2', 'pid' => 'PD-2', 'status' => 'verzonden'])],
            ['tasks/t1', json_encode(['id' => 't1', 'titel' => 'Intern', 'pid' => 'PD-1'])],
            ['extraUsers/k1', json_encode(['id' => 'k1', 'naam' => 'Anna', 'sysrol' => 'Klant', 'mail' => 'anna@example.nl', 'status' => 'actief', 'scope' => 'PD-1'])],
        ]);
        $klant = User::query()->where('email', 'anna@example.nl')->firstOrFail();
        $this->assertSame(UserRole::Klant, $klant->role);
        $this->actingAs($klant)->getJson('/os/staat')->assertStatus(409);
        $klant->forceFill(['must_change_password' => false])->save(); // eigen wachtwoord gekozen

        $rows = collect($this->actingAs($klant)->getJson('/os/staat')->json('rows'))->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);

        $this->assertTrue($rows->has('projects/PD-1'));
        $this->assertFalse($rows->has('projects/PD-2'));
        $this->assertFalse($rows->has('clients/c2'));
        $this->assertFalse($rows->has('invoices/F-2'));
        $this->assertTrue($rows->has('invoices/F-1'));
        $this->assertFalse($rows->has('tasks/t1'));
        $this->assertSame('["PD-1"]', $rows['projects/#order']);
        $this->assertStringNotContainsString('marge 30%', $rows['projects/PD-1']);
        $this->assertStringNotContainsString('planVoorstel', $rows['projects/PD-1'], 'een planning die nog een voorstel is, gaat niet naar de klant');

        // wijzigen: eigen bericht ja, andermans project en facturen nee, prijs en status van de offerte blijven staan
        $sent = json_decode($rows['projects/PD-1'], true);
        $sent['naam'] = 'Gratis badkamer';
        $sent['offerte']['basis'] = 1;
        $sent['offerte']['status'] = 'geaccepteerd';
        $sent['offerte']['signedBy'] = 'Anna';
        $sent['begroting']['options'][0]['s'] = 'akkoord';
        $res = $this->sync($klant, [
            ['klantBerichten/kb1', json_encode(['id' => 'kb1', 'pid' => 'PD-1', 'van' => 'klant', 'txt' => 'Hallo'])],
            ['projects/PD-1', json_encode($sent)],
            ['projects/PD-2', json_encode(['id' => 'PD-2', 'naam' => 'Weg'])],
            ['invoices/F-1', json_encode(['nr' => 'F-1', 'pid' => 'PD-1', 'status' => 'betaald'])],
        ])->json();

        $this->assertEqualsCanonicalizing(['projects/PD-2', 'invoices/F-1'], $res['rejected']);
        $this->assertSame('Hallo', $this->row('klantBerichten/kb1')['txt']);
        $project = $this->row('projects/PD-1');
        $this->assertSame('Badkamer', $project['naam']);
        $this->assertSame(10000, $project['offerte']['basis']);
        $this->assertSame('geaccepteerd', $project['offerte']['status'], 'ondertekenen van een verzonden offerte mag');
        $this->assertSame('akkoord', $project['begroting']['options'][0]['s'], 'akkoord op meerwerk mag');
        $this->assertSame('marge 30%', $project['begroting']['notities']['prive'], 'interne notitie blijft staan');
        $this->assertSame('Tegelwerk', $project['planVoorstel']['regels'][0]['titel'], 'het planningsvoorstel blijft staan als de klant tekent');
        $this->assertSame('verzonden', $this->row('invoices/F-1')['status']);
    }

    public function test_een_klant_krijgt_alleen_de_updates_en_documenten_die_voor_hem_zijn(): void
    {
        $admin = $this->admin();
        $this->sync($admin, [
            ['clients/c1', json_encode(['id' => 'c1', 'naam' => 'Familie de Boer'])],
            ['projects/PD-1', json_encode(['id' => 'PD-1', 'clientId' => 'c1', 'naam' => 'Badkamer', 'kosten' => 3120, 'nacalcFinal' => ['marge' => 21.5],
                'activity' => [
                    ['d' => '2026-11-20', 't' => 'Inkoopfactuur IF-1 goedgekeurd: € 1.200,00', 'k' => 'ok'],
                    ['d' => '2026-11-19', 't' => 'Uitvoering gestart, sloopwerk', 'k' => 'ok'],
                    ['d' => '2026-11-18', 't' => 'Meetingverslag (12:30): de marge staat onder druk', 'k' => 'ok'],
                ],
                'docs' => [
                    ['naam' => 'Gespreksverslag intern.pdf', 'cat' => 'Verslag', 'datum' => '2026-11-18', 'txt' => 'Klant twijfelt over de prijs'],
                    ['naam' => 'Plattegrond.pdf', 'cat' => 'Tekening', 'datum' => '2026-11-10'],
                ]])],
            ['extraUsers/k1', json_encode(['id' => 'k1', 'naam' => 'Anna', 'sysrol' => 'Klant', 'mail' => 'anna@example.nl', 'status' => 'actief', 'scope' => 'PD-1'])],
        ]);
        $klant = User::query()->where('email', 'anna@example.nl')->firstOrFail();
        $klant->forceFill(['must_change_password' => false])->save();

        $rows = collect($this->actingAs($klant)->getJson('/os/staat')->json('rows'))->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);
        $seen = json_decode($rows['projects/PD-1'], true);

        $this->assertSame(['Uitvoering gestart, sloopwerk'], array_column($seen['activity'], 't'), 'alleen mijlpalen, geen inkoop of verslagen');
        $this->assertSame(['Plattegrond.pdf'], array_column($seen['docs'], 'naam'), 'alleen documenten voor de klant');
        $this->assertArrayNotHasKey('kosten', $seen);
        $this->assertArrayNotHasKey('nacalcFinal', $seen);
        $this->assertStringNotContainsString('twijfelt', $rows['projects/PD-1']);

        // De klant stelt een vraag en levert een foto aan: dat komt erbij, het interne deel blijft heel.
        array_unshift($seen['activity'], ['d' => '2026-11-26', 't' => 'Klantvraag via Service Hub: "Wanneer starten jullie?"', 'k' => 'or']);
        array_unshift($seen['docs'], ['naam' => 'meterkast.jpg', 'cat' => 'Klant', 'datum' => '2026-11-26']);
        $this->sync($klant, [['projects/PD-1', json_encode($seen)]])->assertOk();

        $project = $this->row('projects/PD-1');
        $this->assertSame(['Klantvraag via Service Hub: "Wanneer starten jullie?"', 'Inkoopfactuur IF-1 goedgekeurd: € 1.200,00', 'Uitvoering gestart, sloopwerk', 'Meetingverslag (12:30): de marge staat onder druk'], array_column($project['activity'], 't'));
        $this->assertSame(['meterkast.jpg', 'Gespreksverslag intern.pdf', 'Plattegrond.pdf'], array_column($project['docs'], 'naam'));
        $this->assertSame(3120, $project['kosten']);
        $this->assertSame(21.5, $project['nacalcFinal']['marge']);

        // Een scherm dat nog niet is bijgewerkt stuurt de eerdere toevoeging nog eens mee: die komt er niet dubbel in.
        array_unshift($seen['activity'], ['d' => '2026-11-26', 't' => 'Afspraak 30 nov bevestigd door klant', 'k' => 'blue']);
        $this->sync($klant, [['projects/PD-1', json_encode($seen)]])->assertOk();

        $this->assertCount(5, $this->row('projects/PD-1')['activity']);
        $this->assertCount(3, $this->row('projects/PD-1')['docs']);
    }

    public function test_een_partner_ziet_alleen_zijn_eigen_dossier_en_prijsaanvragen_zonder_andermans_prijzen(): void
    {
        $admin = $this->admin();
        $res = $this->sync($admin, [
            ['subs/s1', json_encode(['id' => 's1', 'naam' => 'Tegelwerken', 'mail' => 'info@tegel.example', 'keurmerk' => 'beoordeling', 'docs' => []])],
            ['subs/s2', json_encode(['id' => 's2', 'naam' => 'Schilders', 'mail' => 'info@schilder.example', 'docs' => []])],
            ['rfqs/r1', json_encode(['id' => 'r1', 'pid' => 'PD-1', 'budget' => 5000, 'invited' => ['s1', 's2'], 'bids' => [['sub' => 's2', 'prijs' => 4000]]])],
            ['rfqs/r2', json_encode(['id' => 'r2', 'pid' => 'PD-2', 'invited' => ['s2'], 'bids' => []])],
            ['projects/PD-1', json_encode(['id' => 'PD-1', 'naam' => 'Badkamer', 'plaats' => 'Arnhem', 'begroting' => ['x' => 1], 'clientId' => 'c1'])],
        ])->json();
        $this->assertCount(2, $res['accounts'], 'elke partner met mailadres krijgt een account');

        $partner = User::query()->where('email', 'info@tegel.example')->firstOrFail();
        $this->assertSame(UserRole::Onderaannemer, $partner->role);
        $partner->forceFill(['must_change_password' => false])->save(); // eigen wachtwoord gekozen
        $rows = collect($this->actingAs($partner)->getJson('/os/staat')->json('rows'))->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);

        $this->assertTrue($rows->has('subs/s1'));
        $this->assertFalse($rows->has('subs/s2'));
        $this->assertFalse($rows->has('rfqs/r2'));
        $rfq = json_decode($rows['rfqs/r1'], true);
        $this->assertSame([], $rfq['bids'], 'de prijs van de concurrent komt niet mee');
        $this->assertArrayNotHasKey('budget', $rfq);
        $project = json_decode($rows['projects/PD-1'], true);
        $this->assertNull($project['begroting']);
        $this->assertSame('Arnhem', $project['plaats']);

        // eigen bod uitbrengen mag, het keurmerk zetten niet
        $rfq['bids'][] = ['sub' => 's1', 'prijs' => 3500];
        $sub = json_decode($rows['subs/s1'], true);
        $sub['keurmerk'] = 'goedgekeurd';
        $sub['tel'] = '0612345678';
        $this->sync($partner, [['rfqs/r1', json_encode($rfq)], ['subs/s1', json_encode($sub)]]);

        $this->assertCount(2, $this->row('rfqs/r1')['bids']);
        $this->assertSame(5000, $this->row('rfqs/r1')['budget']);
        $this->assertSame('beoordeling', $this->row('subs/s1')['keurmerk']);
        $this->assertSame('0612345678', $this->row('subs/s1')['tel']);
    }

    public function test_bestanden_worden_opgeslagen_en_alleen_voor_ingelogden_geopend(): void
    {
        Storage::fake('local');
        $user = $this->admin();

        $url = $this->actingAs($user)->post('/os/bestanden', ['file' => UploadedFile::fake()->image('klusfoto.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()->json('url');
        $again = $this->actingAs($user)->post('/os/bestanden', ['file' => UploadedFile::fake()->image('klusfoto.jpg')], ['Accept' => 'application/json'])->json('url');

        $this->assertStringStartsWith('/os/bestanden/', $url);
        $this->actingAs($user)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(OsFile::count(), $url === $again ? 1 : 2);

        auth()->logout();
        $this->get($url)->assertUnauthorized();
    }

    public function test_automations_draaien_in_een_scherm_tegelijk(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->withSession(['x' => 1])->postJson('/os/beurt')->assertJson(['leader' => true]);
        $this->assertTrue(cache()->has('os-lease:automations'));
    }

    public function test_os_reset_begint_opnieuw_met_een_lege_staat(): void
    {
        $user = $this->admin();
        $this->sync($user, [['projects/PD-1', json_encode(['id' => 'PD-1'])]]);

        $this->artisan('os:reset', ['--force' => true])->assertSuccessful();

        $this->assertNull(OsRow::query()->where('path', 'projects/PD-1')->value('value'));
        $this->assertTrue(OsRow::query()->where('path', 'emps/imad')->exists());
    }

    /** Een uitgenodigde partner met account, klaar om in te loggen. */
    private function partner(User $admin, string $id = 's1', string $mail = 'piet@loodgieter.example'): User
    {
        $this->sync($admin, [['subs/'.$id, json_encode([
            'id' => $id, 'naam' => 'Loodgietersbedrijf Jansen', 'contact' => 'Piet Jansen', 'mail' => $mail, 'vak' => 'Loodgieter',
            'keurmerk' => 'nieuw', 'tarief' => 0, 'docs' => [], 'checks' => [], 'inv' => ['d' => '2026-11-27', 'stap' => 0, 'done' => false],
        ])]]);
        $partner = User::query()->where('email', $mail)->firstOrFail();
        $partner->forceFill(['must_change_password' => false])->save();

        return $partner;
    }

    public function test_een_partner_meldt_zichzelf_aan_en_komt_dan_in_beoordeling(): void
    {
        $admin = $this->admin();
        $partner = $this->partner($admin);
        $this->assertSame('Piet Jansen', $partner->name, 'het account heet naar de contactpersoon');

        // tijdens het aanmelden: eigen gegevens en tarief, maar niet zelf het keurmerk
        $sub = $this->row('subs/s1');
        $this->sync($partner, [['subs/s1', json_encode([...$sub, 'kvk' => '12345678', 'tarief' => 58, 'keurmerk' => 'goedgekeurd', 'rating' => 5, 'inv' => ['d' => '2026-11-27', 'stap' => 3, 'done' => false]])]]);
        $this->assertSame('12345678', $this->row('subs/s1')['kvk']);
        $this->assertSame(58, $this->row('subs/s1')['tarief']);
        $this->assertSame('nieuw', $this->row('subs/s1')['keurmerk']);
        $this->assertArrayNotHasKey('rating', $this->row('subs/s1'));

        // afronden: van nieuw naar in beoordeling
        $sub = $this->row('subs/s1');
        $this->sync($partner, [['subs/s1', json_encode([...$sub, 'keurmerk' => 'beoordeling', 'inv' => ['d' => '2026-11-27', 'stap' => 6, 'done' => true]])]]);
        $this->assertSame('beoordeling', $this->row('subs/s1')['keurmerk']);

        // daarna ligt het tarief vast en beslist Renovion over het keurmerk
        $sub = $this->row('subs/s1');
        $this->sync($partner, [['subs/s1', json_encode([...$sub, 'tarief' => 95, 'keurmerk' => 'goedgekeurd'])]]);
        $this->assertSame(58, $this->row('subs/s1')['tarief']);
        $this->assertSame('beoordeling', $this->row('subs/s1')['keurmerk']);
    }

    public function test_een_partner_geeft_beschikbaarheid_door_stelt_vragen_en_dient_facturen_in(): void
    {
        $admin = $this->admin();
        $partner = $this->partner($admin);
        $ander = $this->partner($admin, 's2', 'info@schilder.example');
        $this->sync($admin, [
            ['partnerBesch/pb-ander', json_encode(['id' => 'pb-ander', 'sub' => 's2', 'van' => '2026-12-01', 'tot' => '2026-12-02', 'status' => 'open'])],
            ['partnerBesch/#order', json_encode(['pb-ander'])],
            ['partnerVragen/pq-ander', json_encode(['id' => 'pq-ander', 'sub' => 's2', 'van' => 'partner', 'txt' => 'Vraag van de ander'])],
        ]);

        $res = $this->sync($partner, [
            ['partnerBesch/pb1', json_encode(['id' => 'pb1', 'sub' => 's1', 'van' => '2026-12-07', 'tot' => '2026-12-11', 'note' => 'Hele dagen', 'status' => 'gekoppeld', 'pid' => 'PD-9'])],
            ['partnerBesch/#order', json_encode(['pb1'])],
            ['partnerBesch/pb2', json_encode(['id' => 'pb2', 'sub' => 's2', 'van' => '2026-12-07', 'tot' => '2026-12-11'])],
            ['partnerBesch/pb3', json_encode(['id' => 'pb3', 'sub' => 's1', 'van' => '2026-12-11', 'tot' => '2026-12-07'])],
            ['partnerVragen/pq1', json_encode(['id' => 'pq1', 'sub' => 's1', 'van' => 'renovion', 'naam' => 'Piet', 'txt' => 'Waar ligt de sleutel?'])],
            ['partnerFact/pf1', json_encode(['id' => 'pf1', 'sub' => 's1', 'nr' => '2026-014', 'bedrag' => 1280.5, 'btw' => 0, 'status' => 'betaald', 'url' => '/os/bestanden/abc'])],
            ['partnerFact/pf2', json_encode(['id' => 'pf2', 'sub' => 's1', 'nr' => '2026-015', 'bedrag' => 100, 'url' => 'https://elders.example/factuur.pdf'])],
        ])->json();

        $this->assertEqualsCanonicalizing(['partnerBesch/pb2', 'partnerBesch/pb3', 'partnerFact/pf2'], $res['rejected']);
        $this->assertSame('open', $this->row('partnerBesch/pb1')['status'], 'koppelen doet Renovion');
        $this->assertArrayNotHasKey('pid', $this->row('partnerBesch/pb1'));
        $this->assertSame('partner', $this->row('partnerVragen/pq1')['van']);
        $this->assertSame('ingediend', $this->row('partnerFact/pf1')['status']);
        $this->assertSame(1280.5, $this->row('partnerFact/pf1')['bedrag']);

        // hij ziet alleen wat van zijn eigen bedrijf is
        $rows = collect($this->actingAs($partner)->getJson('/os/staat')->json('rows'))->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);
        $this->assertTrue($rows->has('partnerBesch/pb1'));
        $this->assertFalse($rows->has('partnerBesch/pb-ander'));
        $this->assertFalse($rows->has('partnerVragen/pq-ander'));
        $this->assertSame(['pb1'], json_decode($rows['partnerBesch/#order'], true));

        // Renovion koppelt en keurt goed; daarna kan de partner het niet meer intrekken of aanpassen
        $this->sync($admin, [
            ['partnerBesch/pb1', json_encode([...$this->row('partnerBesch/pb1'), 'status' => 'gekoppeld', 'pid' => 'PD-1'])],
            ['partnerFact/pf1', json_encode([...$this->row('partnerFact/pf1'), 'status' => 'goedgekeurd'])],
            ['partnerVragen/pq2', json_encode(['id' => 'pq2', 'sub' => 's1', 'van' => 'renovion', 'naam' => 'Imad', 'txt' => 'Bij de buren op 14.'])],
        ]);
        $this->sync($partner, [
            ['partnerBesch/pb1', json_encode([...$this->row('partnerBesch/pb1'), 'status' => 'ingetrokken'])],
            ['partnerFact/pf1', json_encode([...$this->row('partnerFact/pf1'), 'status' => 'betaald', 'bedrag' => 9999])],
            ['partnerVragen/pq2', json_encode(['id' => 'pq2', 'sub' => 's1', 'van' => 'renovion', 'txt' => 'Aangepast antwoord'])],
        ]);
        $this->assertSame('gekoppeld', $this->row('partnerBesch/pb1')['status']);
        $this->assertSame('goedgekeurd', $this->row('partnerFact/pf1')['status']);
        $this->assertSame(1280.5, $this->row('partnerFact/pf1')['bedrag']);
        $this->assertSame('Bij de buren op 14.', $this->row('partnerVragen/pq2')['txt']);

        // een klant krijgt niets van de partners te zien
        $this->sync($admin, [['extraUsers/u1', json_encode(['id' => 'u1', 'naam' => 'Familie de Boer', 'sysrol' => 'Klant', 'mail' => 'klant@example.test', 'status' => 'actief', 'scope' => 'PD-1'])]]);
        $klant = User::query()->where('email', 'klant@example.test')->firstOrFail();
        $klant->forceFill(['must_change_password' => false])->save();
        $paths = collect($this->actingAs($klant)->getJson('/os/staat')->json('rows'))->map(fn ($r) => $r[0]);
        $this->assertSame([], $paths->filter(fn ($p) => str_starts_with($p, 'partner') && ! str_ends_with($p, '/#order'))->values()->all());
        $this->assertNotNull($ander);
    }

    public function test_een_ingeplande_partner_krijgt_een_vakman_in_de_planning_en_vult_zijn_werkbon_in(): void
    {
        $admin = $this->admin();
        $partner = $this->partner($admin);

        // bij het keurmerk of het koppelen: een vakman van het type partner met hetzelfde mailadres; het account verhuist mee
        $this->sync($admin, [
            ['emps/e1', json_encode(['id' => 'e1', 'naam' => 'Piet Jansen', 'rol' => 'Loodgieter', 'type' => 'partner', 'sysrol' => 'Onderaannemer', 'status' => 'actief', 'mail' => 'piet@loodgieter.example'])],
            ['subs/s1', json_encode([...$this->row('subs/s1'), 'empId' => 'e1', 'keurmerk' => 'goedgekeurd'])],
            ['projects/PD-1', json_encode(['id' => 'PD-1', 'naam' => 'Badkamer De Boer', 'adres' => 'Kerkstraat 1', 'plaats' => 'Arnhem', 'begroting' => ['x' => 1]])],
            ['blocks/b1', json_encode(['id' => 'b1', 'emp' => 'e1', 'pid' => 'PD-1', 'titel' => 'Leidingwerk', 'start' => '2026-12-07', 'end' => '2026-12-08'])],
            ['werkbonnen/WB-1', json_encode(['nr' => 'WB-1', 'pid' => 'PD-1', 'emp' => 'e1', 'blockId' => 'b1', 'titel' => 'Leidingwerk', 'status' => 'uitgegeven', 'begrUren' => 16, 'uren' => 0, 'situatie' => 'Sleutel bij de buren', 'checklist' => [['t' => 'Persproef uitgevoerd', 'd' => false]], 'opm' => '', 'obstakels' => '', 'getekend' => ''])],
        ]);
        $this->assertSame(1, User::query()->where('email', 'piet@loodgieter.example')->count());
        $partner->refresh();
        $this->assertSame('e1', $partner->person_id);

        $rows = collect($this->actingAs($partner)->getJson('/os/staat')->json('rows'))->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);
        $this->assertTrue($rows->has('subs/s1'), 'zijn eigen dossier, nu via de vakman');
        $this->assertTrue($rows->has('blocks/b1'));
        $this->assertSame('Kerkstraat 1', json_decode($rows['projects/PD-1'], true)['adres']);
        $this->assertNull(json_decode($rows['projects/PD-1'], true)['begroting']);
        $wb = json_decode($rows['werkbonnen/WB-1'], true);
        $this->assertSame('Sleutel bij de buren', $wb['situatie']);

        // invullen en indienen: uren, checklist, opmerking, obstakel en wie aftekende; de voorbereiding blijft van kantoor
        $this->sync($partner, [['werkbonnen/WB-1', json_encode([...$wb, 'status' => 'controle', 'uren' => 15, 'checklist' => [['t' => 'Persproef uitgevoerd', 'd' => true]], 'opm' => 'Klaar', 'obstakels' => 'Afvoer lag hoger', 'getekend' => 'Mevrouw de Boer', 'situatie' => 'Aangepast', 'begrUren' => 99])]]);
        $saved = $this->row('werkbonnen/WB-1');
        $this->assertSame('controle', $saved['status']);
        $this->assertSame(15, $saved['uren']);
        $this->assertSame('Afvoer lag hoger', $saved['obstakels']);
        $this->assertSame('Mevrouw de Boer', $saved['getekend']);
        $this->assertSame('Sleutel bij de buren', $saved['situatie']);
        $this->assertSame(16, $saved['begrUren']);

        // goedkeuren kan hij niet zelf
        $this->sync($partner, [['werkbonnen/WB-1', json_encode([...$saved, 'status' => 'goedgekeurd'])]]);
        $this->assertSame('controle', $this->row('werkbonnen/WB-1')['status']);
    }
}
