<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\OsFile;
use App\Models\OsRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * De gedeelde staat van Renovion OS: inloggen, ophalen, opslaan, bijwerken
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

    public function test_een_klant_ziet_alleen_zijn_eigen_project_en_kan_niets_anders_wijzigen(): void
    {
        $admin = $this->admin();
        $this->sync($admin, [
            ['clients/c1', json_encode(['id' => 'c1', 'naam' => 'Familie de Boer'])],
            ['clients/c2', json_encode(['id' => 'c2', 'naam' => 'Bakkerij Jansen'])],
            ['projects/PD-1', json_encode(['id' => 'PD-1', 'clientId' => 'c1', 'naam' => 'Badkamer', 'begroting' => ['notities' => ['prive' => 'marge 30%', 'vragen' => []], 'options' => [['d' => 'Vloerverwarming', 's' => 'optie']]], 'offerte' => ['status' => 'verzonden', 'basis' => 10000], 'activity' => []])],
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
        $this->assertSame('verzonden', $this->row('invoices/F-1')['status']);
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
}
