<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\OsRow;
use App\Models\User;
use App\Os\Ai\ActionException;
use App\Os\Ai\Actions;
use App\Os\Ai\Agents;
use App\Os\Ai\ReadTools;
use App\Os\Ai\Signals;
use App\Os\Ai\StateReader;
use App\Os\StateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Nova en de agents zonder echte AI-aanroep: wat er gecontroleerd, uitgevoerd
 * en afgeschermd wordt.
 */
class OsAiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Imad', 'role' => UserRole::Admin]);
        app(StateStore::class)->ensureInstalled();
        $this->rows([
            'clients/c1' => ['id' => 'c1', 'naam' => 'Familie de Boer', 'tel' => '', 'mail' => 'anna@example.nl'],
            'projects/PD-1' => ['id' => 'PD-1', 'naam' => 'Badkamer de Boer', 'clientId' => 'c1', 'phase' => 2, 'intake' => new \stdClass, 'activity' => [],
                'begroting' => ['rate' => 50, 'staart' => [], 'chapters' => [['code' => '10', 'name' => 'Sloop', 'pars' => [['lines' => [['id' => 'l1', 't' => 'line', 'd' => 'Tegels verwijderen', 'q' => 10, 'u' => 'm2', 'n' => 0, 'm' => 0, 'e' => 0, 'o' => 0, 'v' => 0]]]]]]]],
            'projects/#order' => ['PD-1'],
        ]);
    }

    private function rows(array $rows): void
    {
        app(StateStore::class)->apply($this->admin, array_map(fn ($p, $v) => [$p, json_encode($v)], array_keys($rows), $rows));
    }

    private function row(string $path): mixed
    {
        return json_decode((string) OsRow::query()->where('path', $path)->value('value'), true);
    }

    private function actions(?User $user = null): Actions
    {
        return new Actions(new StateReader($user ?? $this->admin), now()->toDateString());
    }

    public function test_een_voorstel_met_een_onbekend_project_of_persoon_wordt_teruggestuurd(): void
    {
        $this->expectException(ActionException::class);
        $this->actions()->check([['type' => 'taak', 'titel' => 'Bellen', 'project_id' => 'PD-999']]);
    }

    public function test_een_voorstel_zonder_verplicht_veld_wordt_teruggestuurd(): void
    {
        try {
            $this->actions()->check([['type' => 'afspraak', 'titel' => 'Intake', 'datum' => '2026-11-23']]);
            $this->fail('verwachtte een fout');
        } catch (ActionException $e) {
            $this->assertStringContainsString('tijd', $e->getMessage());
        }
    }

    public function test_bevestigde_acties_komen_echt_in_de_staat(): void
    {
        $result = $this->actions()->execute($this->admin, [
            ['type' => 'taak', 'titel' => 'Tegels uitzoeken', 'project_id' => 'PD-1', 'eigenaar' => 'imad', 'deadline' => '2026-11-27', 'prioriteit' => 'hoog'],
            ['type' => 'afspraak', 'titel' => 'Terugbellen', 'datum' => '2026-11-23', 'tijd' => '09:00', 'project_id' => 'PD-1', 'soort' => 'terug'],
            ['type' => 'begrotingsregel', 'project_id' => 'PD-1', 'regel_id' => 'l1', 'uren_per_eenheid' => 0.5, 'materiaal_per_eenheid' => 4],
            ['type' => 'klantbericht', 'project_id' => 'PD-1', 'tekst' => 'Wij bellen u maandag.'],
        ]);

        $this->assertSame([], $result['rejected']);
        $tasks = collect(OsRow::query()->where('collection', 'tasks')->where('path', 'not like', '%#order')->pluck('value'))->map(fn ($v) => json_decode($v, true));
        $this->assertSame('Tegels uitzoeken', $tasks->first()['titel']);
        $this->assertSame('hoog', $tasks->first()['prio']);
        $this->assertCount(1, $this->row('tasks/#order'));
        $project = $this->row('projects/PD-1');
        $this->assertSame(0.5, $project['begroting']['chapters'][0]['pars'][0]['lines'][0]['n']);
        $this->assertCount(4, $project['activity']);
        $this->assertStringContainsString('"intake":{}', OsRow::query()->where('path', 'projects/PD-1')->value('value'), 'een leeg object blijft een object');
        $this->assertSame('09:00', collect(OsRow::query()->where('collection', 'agenda')->where('path', 'not like', '%#order')->pluck('value'))->map(fn ($v) => json_decode($v, true))->first()['tijd']);
        $this->assertSame('renovion', collect(OsRow::query()->where('collection', 'klantBerichten')->where('path', 'not like', '%#order')->pluck('value'))->map(fn ($v) => json_decode($v, true))->first()['van']);
    }

    public function test_een_nieuwe_aanvraag_maakt_klant_project_en_taak_met_lopend_nummer(): void
    {
        $this->actions()->execute($this->admin, [['type' => 'aanvraag', 'naam' => 'Familie Visser', 'plaats' => 'Ede', 'soort_werk' => 'Keukenrenovatie', 'mailadres' => 'visser@example.nl']]);

        $pid = 'PD-'.now()->year.'-0001';
        $this->assertSame('Keukenrenovatie', $this->row('projects/'.$pid)['type']);
        $this->assertSame($pid, $this->row('projects/#order')[0]);
        $this->assertSame(1, $this->row('seq')['pd']);
    }

    public function test_een_lead_komt_in_het_crm_zonder_dossier_en_nova_ziet_hem_als_lead(): void
    {
        $result = $this->actions()->execute($this->admin, [['type' => 'lead', 'naam' => 'Bouwbedrijf Jansen', 'soort' => 'Zakelijk', 'contactpersoon' => 'Henk Jansen', 'plaats' => 'Arnhem', 'soort_werk' => 'Stucwerk', 'budget' => 12500, 'tekst' => 'Gesproken op een netwerkborrel', 'datum' => '2026-11-30', 'tijd' => '10:00']]);

        $this->assertSame([], $result['rejected']);
        $lead = collect(OsRow::query()->where('collection', 'clients')->where('path', 'not like', '%#order')->pluck('value'))->map(fn ($v) => json_decode($v, true))->firstWhere('naam', 'Bouwbedrijf Jansen');
        $this->assertSame('nieuw', $lead['lead']['status']);
        $this->assertSame('Stucwerk', $lead['lead']['interesse']);
        $this->assertSame(12500.0, (float) $lead['lead']['waarde']);
        $this->assertSame('2026-11-30', $this->row('leadNext/'.$lead['id'])['d']);
        $this->assertSame(['PD-1'], $this->row('projects/#order'), 'een lead krijgt geen dossier');
        $this->assertFalse(Actions::isSafe([['type' => 'lead', 'naam' => 'x']]));

        $found = (new ReadTools(new StateReader($this->admin), now()->toDateString()))->run('zoeken', ['zoekterm' => 'Jansen']);
        $this->assertStringContainsString('lead zonder dossier', $found);
        $this->assertStringNotContainsString('lead zonder dossier', (new ReadTools(new StateReader($this->admin), now()->toDateString()))->run('zoeken', ['zoekterm' => 'de Boer']));
    }

    public function test_alleen_veilige_interne_acties_mogen_zonder_bevestiging(): void
    {
        $this->assertTrue(Actions::isSafe([['type' => 'taak'], ['type' => 'notitie']]));
        $this->assertFalse(Actions::isSafe([['type' => 'taak'], ['type' => 'klantbericht']]));
        $this->assertFalse(Actions::isSafe([['type' => 'mail']]));
    }

    public function test_nova_voert_alleen_een_eigen_bewaard_voorstel_uit(): void
    {
        Cache::put('nova-voorstel:p1', ['user' => $this->admin->id, 'acties' => [['type' => 'notitie', 'project_id' => 'PD-1', 'tekst' => 'Klant belt terug']]], 60);
        $other = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($other)->postJson('/os/nova/p1/bevestigen')->assertStatus(422);
        $this->actingAs($this->admin)->postJson('/os/nova/p1/bevestigen')->assertOk()->assertJsonPath('rejected', []);
        $this->actingAs($this->admin)->postJson('/os/nova/p1/bevestigen')->assertStatus(422);
        $this->assertStringContainsString('Klant belt terug', $this->row('projects/PD-1')['activity'][0]['t']);
    }

    public function test_een_agentvoorstel_keurt_alleen_de_juiste_rol_of_directie_goed(): void
    {
        $this->rows([
            'ai.approvals/apr_1' => ['id' => 'apr_1', 'run_id' => 'run_1', 'pid' => 'PD-1', 'action' => 'nova_actions', 'role' => 'Projectleider', 'status' => 'open', 'expires' => '2099-01-01',
                'acties' => [['type' => 'taak', 'titel' => 'Planning bijwerken', 'project_id' => 'PD-1']], 'changes' => ['Taak']],
            'ai.runs/run_1' => ['run_id' => 'run_1', 'agent' => 'milo', 'status' => 'waiting_for_approval', 'steps' => []],
        ]);
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($sales)->postJson('/os/agents/goedkeuringen/apr_1', ['besluit' => 'ok'])->assertStatus(422);
        $this->actingAs($this->admin)->postJson('/os/agents/goedkeuringen/apr_1', ['besluit' => 'ok'])->assertOk()->assertJsonPath('status', 'approved');

        $this->assertSame('approved', $this->row('ai.approvals/apr_1')['status']);
        $this->assertSame('completed', $this->row('ai.runs/run_1')['status']);
        $this->actingAs($this->admin)->postJson('/os/agents/goedkeuringen/apr_1', ['besluit' => 'ok'])->assertStatus(422);
    }

    public function test_signalen_voegen_een_dubbele_planning_per_persoon_samen(): void
    {
        $day = now()->addDays(3)->toDateString();
        $this->rows([
            'blocks/b1' => ['id' => 'b1', 'emp' => 'imad', 'pid' => 'PD-1', 'titel' => 'Sloop', 'start' => $day, 'end' => $day],
            'blocks/b2' => ['id' => 'b2', 'emp' => 'imad', 'pid' => 'PD-1', 'titel' => 'Tegelwerk', 'start' => $day, 'end' => $day],
            'blocks/b3' => ['id' => 'b3', 'emp' => 'imad', 'pid' => '', 'titel' => 'Intern', 'start' => $day, 'end' => $day],
            'blocks/#order' => ['b1', 'b2', 'b3'],
        ]);

        $milo = array_values(array_filter((new Signals(new StateReader($this->admin), now()->toDateString()))->all(), fn ($s) => $s['agent'] === 'milo'));

        $this->assertCount(1, $milo);
        $this->assertStringContainsString('b3', $milo[0]['tekst']);
    }

    public function test_een_websiteaanvraag_wordt_een_klant_met_dossier(): void
    {
        config(['services.renovion.lead_intake_token' => 'geheim']);
        $body = ['name' => 'Kevin Jansen', 'email' => 'kevin@example.nl', 'phone' => '0650583365', 'city' => 'Utrecht', 'service' => 'Schilderwerk', 'message' => 'Kozijnen schilderen, eerst advies.'];

        $this->postJson('/api/aanvragen', $body)->assertUnauthorized();
        $id = $this->postJson('/api/aanvragen', $body, ['X-Intake-Token' => 'geheim'])->assertCreated()->json('id');
        $this->postJson('/api/aanvragen', $body, ['X-Intake-Token' => 'geheim'])->assertCreated()->assertJsonPath('id', $id);

        $project = $this->row('projects/'.$id);
        $this->assertSame('Schilderwerk Kevin Jansen', $project['naam']);
        $this->assertSame(0, $project['phase']);
        $this->assertSame('Website', $project['bron']);
        $this->assertSame('Kozijnen schilderen, eerst advies.', $project['intake']['wensen']);
        $client = $this->row('clients/'.$project['clientId']);
        $this->assertSame('0650583365', $client['tel']);

        // Zelfde mailadres, andere vraag: dezelfde klant, een tweede dossier
        $second = $this->postJson('/api/aanvragen', [...$body, 'service' => 'Badkamerrenovatie', 'message' => 'Ook de badkamer.'], ['X-Intake-Token' => 'geheim'])->json('id');
        $this->assertNotSame($id, $second);
        $this->assertSame($project['clientId'], $this->row('projects/'.$second)['clientId']);
        $this->assertSame('Badkamer Kevin Jansen', $this->row('projects/'.$second)['naam']);
    }

    public function test_klant_en_partner_kunnen_nova_niet_gebruiken(): void
    {
        $klant = User::factory()->create(['role' => UserRole::Klant]);

        $this->actingAs($klant)->postJson('/os/nova', ['vraag' => 'Wat is de marge?'])->assertForbidden();
        $this->actingAs($klant)->postJson('/os/agents/ronde')->assertForbidden();
    }

    public function test_zonder_sleutel_zegt_nova_dat_eerlijk(): void
    {
        config(['services.anthropic.key' => null]);

        $this->actingAs($this->admin)->postJson('/os/nova', ['vraag' => 'Hoi'])->assertStatus(503)->assertJsonPath('message', fn ($m) => str_contains($m, 'ANTHROPIC_API_KEY'));
        $this->assertSame(UserRole::Admin, Agents::systemUser()->role);
    }
}
