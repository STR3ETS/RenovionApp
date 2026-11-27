<?php

namespace App\Os\Ai;

use App\Enums\UserRole;
use App\Models\OsRow;
use App\Models\User;
use App\Os\StateStore;
use Illuminate\Support\Facades\DB;

/**
 * De agents van Renovion Groep (Lex, Cato, Prep, Milo, Dex, Finn, Liv en Nova)
 * aan het werk op de echte gegevens.
 *
 * Signals bepaalt waar werk ligt. Per signaal kijkt de agent met Claude in
 * het dossier en doet een voorstel. Het niveau van de skill (in Skills &
 * SOP's) bepaalt wat er dan gebeurt:
 *  - observeren: alleen een signaal met advies, niets wordt uitgevoerd;
 *  - voorbereiden: het voorstel wacht in Goedkeuringen op de juiste rol;
 *  - protocol / begrensd: interne acties (taak, notitie, chatbericht) voert
 *    de agent zelf uit; berichten naar klanten en al het andere wacht op
 *    goedkeuring.
 * Uitvoeringen, goedkeuringen en het logboek staan in dezelfde vorm als in
 * het prototype (ai.runs, ai.approvals, ai.audit), dus de schermen Agents,
 * Goedkeuringen en Audit tonen ze vanzelf.
 */
final class Agents
{
    public const AGENTS = [
        'nova' => ['naam' => 'Nova', 'spec' => 'Orkestratie', 'rol' => 'Directie', 'taak' => 'Opdrachten begrijpen, routeren, samenvatten, escaleren en overdracht bewaken'],
        'lex' => ['naam' => 'Lex', 'spec' => 'Sales en intake', 'rol' => 'Sales', 'taak' => 'Aanvraag, opname voorbereiden, ontbrekende gegevens en offerteopvolging'],
        'cato' => ['naam' => 'Cato', 'spec' => 'Calculatie en offerte', 'rol' => 'Calculator', 'taak' => 'Hoeveelheden structureren, prijsregels voorstellen, ontbrekende posten signaleren'],
        'prep' => ['naam' => 'Prep', 'spec' => 'Werkvoorbereiding en inkoop', 'rol' => 'Werkvoorbereider', 'taak' => 'Tekeningen, keuzes, werkpakketten, materialen en leveranciersstatus'],
        'milo' => ['naam' => 'Milo', 'spec' => 'Planning en projectbewaking', 'rol' => 'Projectleider', 'taak' => 'Afhankelijkheden, capaciteit, deadlines en herplanningsvoorstellen'],
        'dex' => ['naam' => 'Dex', 'spec' => 'Uitvoering en bewijs', 'rol' => 'Uitvoerder', 'taak' => 'Checklists, fotokoppeling, issues en volledigheidscontrole'],
        'finn' => ['naam' => 'Finn', 'spec' => 'Budget en administratie', 'rol' => 'Administratie', 'taak' => 'Uren, kosten, verplichtingen, meerwerk en termijnsignalen'],
        'liv' => ['naam' => 'Liv', 'spec' => 'Klantzorg en nazorg', 'rol' => 'Projectleider', 'taak' => 'Klantupdates, kennisantwoorden, restpunten, enquête en reviewopvolging'],
    ];

    /** Standaardniveau en wie goedkeurt, zoals SKILLS in de app. */
    public const SKILLS = [
        'complete_intake' => ['naam' => 'Intake aanvullen en offerte opvolgen', 'level' => 'voorbereiden', 'appr' => 'Sales'],
        'propose_calculation_lines' => ['naam' => 'Calculatieregels voorstellen', 'level' => 'voorbereiden', 'appr' => 'Calculator'],
        'check_material_status' => ['naam' => 'Materiaalstatus bewaken', 'level' => 'observeren', 'appr' => null],
        'propose_schedule_change' => ['naam' => 'Herplanning voorstellen', 'level' => 'voorbereiden', 'appr' => 'Projectleider'],
        'send_internal_reminder' => ['naam' => 'Interne herinnering sturen', 'level' => 'protocol', 'appr' => null],
        'prepare_customer_update' => ['naam' => 'Klantupdate voorbereiden', 'level' => 'voorbereiden', 'appr' => 'Projectleider'],
        'check_work_package_readiness' => ['naam' => 'Werkbon en gereedmelding controleren', 'level' => 'protocol', 'appr' => 'Uitvoerder'],
        'budget_forecast' => ['naam' => 'Budget en betalingen bewaken', 'level' => 'observeren', 'appr' => null],
        'triage_care_question' => ['naam' => 'Klantvraag triëren', 'level' => 'protocol', 'appr' => null],
    ];

    /** Wat een agent per keer maximaal oppakt, zodat een volle achterstand niet in één keer veel kost. */
    public const MAX_PER_ROUND = 6;

    /**
     * Laat de agents een ronde werken.
     *
     * @return list<array{agent: string, signaal: string, uitkomst: string}>
     */
    public function round(?int $limit = null): array
    {
        if (! Claude::enabled()) {
            throw new AiUnavailable('De agents hebben nog geen AI-sleutel. Zet ANTHROPIC_API_KEY in .env.');
        }

        $system = self::systemUser();
        $state = new StateReader($system);
        $today = now()->toDateString();
        $cfg = $state->whole['ai']['cfg'] ?? [];
        if ($cfg['stop'] ?? false) {
            return [];
        }

        $done = DB::table('os_agent_runs')->pluck('signal_key')->flip();
        $out = [];
        foreach ((new Signals($state, $today))->all() as $signal) {
            if (count($out) >= ($limit ?? self::MAX_PER_ROUND)) {
                break;
            }
            $skill = self::SKILLS[$signal['skill']] ?? null;
            $setting = $cfg['skills'][$signal['skill']] ?? [];
            if (! $skill || isset($done[$signal['key']]) || ($cfg['agents'][$signal['agent']]['paused'] ?? false) || ($setting['paused'] ?? false)) {
                continue;
            }
            $out[] = $this->work($system, $state, $signal, $setting['level'] ?? $skill['level'], $today);
            $state = new StateReader($system); // de volgende agent ziet wat deze deed
        }

        return $out;
    }

    /** @return array{agent: string, signaal: string, uitkomst: string} */
    private function work(User $system, StateReader $state, array $signal, string $level, string $today): array
    {
        $agent = self::AGENTS[$signal['agent']];
        $skill = self::SKILLS[$signal['skill']];
        $actions = new Actions($state, $today);
        $actions->defaultOwner = $this->ownerOf($state, $skill['appr'] ?? $agent['rol'], $signal['pid']);

        try {
            $result = (new Claude)->run($this->prompt($state, $signal, $agent, $skill, $level, $today), [[
                'role' => 'user',
                'content' => "Signaal: {$signal['tekst']}".($signal['pid'] ? "\nProject: {$signal['pid']}" : '')."\n\nBekijk de gegevens en handel volgens je opdracht.",
            ]], new ReadTools($state, $today), $actions, 'medium', 8);
        } catch (AiUnavailable $e) {
            report($e);

            return ['agent' => $agent['naam'], 'signaal' => $signal['tekst'], 'uitkomst' => 'niet gelukt: '.$e->getMessage()];
        }

        $now = now()->format('Y-m-d\TH:i');
        $runId = 'run_'.substr(bin2hex(random_bytes(4)), 0, 7);
        $proposals = $result['voorstellen'];
        $autoExecute = in_array($level, ['protocol', 'begrensd'], true);
        $rows = [];
        $steps = [['ts' => $now, 't' => 'Signaal opgepakt: '.$signal['tekst']]];
        foreach ($result['stappen'] as $step) {
            if (! str_starts_with($step, 'voorstel')) {
                $steps[] = ['ts' => $now, 't' => 'Gelezen: '.$step];
            }
        }

        $executed = [];
        $waiting = [];
        foreach ($proposals as $p) {
            if ($level === 'observeren') {
                $steps[] = ['ts' => $now, 't' => 'Advies: '.implode('; ', $p['changes'])];
            } elseif ($autoExecute && $p['safe']) {
                $done = $actions->execute($system, $p['acties'], $signal['agent']);
                $executed = [...$executed, ...$done['samenvatting']];
                $steps[] = ['ts' => $now, 't' => 'Zelf uitgevoerd (niveau '.$level.'): '.implode('; ', $done['samenvatting'])];
            } else {
                $waiting[] = $p;
            }
        }

        $approvals = [];
        foreach ($waiting as $p) {
            $aprId = 'apr_'.substr(bin2hex(random_bytes(4)), 0, 7);
            $role = $skill['appr'] ?? $agent['rol'];
            $approvals[] = $aprId;
            $rows['ai.approvals/'.$aprId] = [
                'id' => $aprId, 'run_id' => $runId, 'pid' => $signal['pid'], 'action' => 'nova_actions', 'label' => $p['begrepen'] ?: implode('; ', $p['changes']),
                'role' => $role, 'version' => 1, 'hash' => substr(sha1(json_encode($p['acties'])), 0, 12), 'amount' => 0, 'created' => $now,
                'expires' => date('Y-m-d', strtotime($today.' +7 days')), 'status' => 'open', 'scope' => '', 'acties' => $p['acties'], 'changes' => $p['changes'], 'gevolg' => $p['gevolg'],
            ];
            $steps[] = ['ts' => $now, 't' => 'Goedkeuring gevraagd aan '.$role.': '.implode('; ', $p['changes'])];
        }

        $status = $approvals ? 'waiting_for_approval' : 'completed';
        $rows['ai.runs/'.$runId] = [
            'run_id' => $runId, 'organization_id' => 'org_renovion', 'project_id' => $signal['pid'], 'agent' => $signal['agent'], 'skill' => $signal['skill'],
            'sop_version' => null, 'status' => $status, 'human_owner_role' => $agent['rol'], 'subject_version' => 1, 'evidence_refs' => [],
            'proposed_action' => $approvals ? 'nova_actions' : null, 'approval_required' => (bool) $approvals, 'next_action' => $approvals ? 'await_approval:'.$approvals[0] : null,
            'wacht' => $approvals ? 'Goedkeuring '.($skill['appr'] ?? $agent['rol']) : '', 'taak' => mb_substr($signal['tekst'], 0, 140), 'started' => $now, 'last' => $now,
            'kosten' => $result['kosten'], 'resultaat' => $result['tekst'] ?: ($executed ? 'Uitgevoerd: '.implode('; ', $executed) : 'Bekeken, geen actie nodig.'), 'steps' => $steps,
        ];
        $event = $this->event('run.started', $signal, $runId, 'agent:'.$signal['agent'], $now);
        $rows['ai.audit/'.$event['event_id']] = $event;
        foreach ($approvals as $aprId) {
            $event = $this->event('approval.requested', $signal, $runId, 'agent:'.$signal['agent'], $now, ['approval_id' => $aprId]);
            $rows['ai.audit/'.$event['event_id']] = $event;
        }
        if ($level === 'observeren' || $approvals) {
            $k = 'k'.bin2hex(random_bytes(4));
            $rows['notifs/'.$k] = ['t' => $agent['naam'].': '.mb_substr($result['tekst'] ?: $signal['tekst'], 0, 160), 'd' => $today, '_k' => $k];
        }

        $this->write($system, $rows);
        DB::table('os_agent_runs')->insert([
            'signal_key' => mb_substr($signal['key'], 0, 191), 'agent' => $signal['agent'], 'skill' => $signal['skill'], 'project_id' => $signal['pid'],
            'run_id' => $runId, 'uitkomst' => $approvals ? 'wacht op goedkeuring' : ($executed ? 'uitgevoerd' : 'afgerond'), 'kosten' => $result['kosten'],
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['agent' => $agent['naam'], 'signaal' => $signal['tekst'], 'uitkomst' => $approvals ? 'wacht op goedkeuring' : ($executed ? 'uitgevoerd' : 'afgerond')];
    }

    /**
     * Een mens beslist over een voorstel van een agent.
     *
     * @return array{rev: int, status: string, samenvatting: list<string>}
     */
    public function decide(User $user, string $approvalId, bool $approve): array
    {
        $approval = json_decode((string) OsRow::query()->where('path', 'ai.approvals/'.$approvalId)->value('value'), true);
        if (! is_array($approval) || ($approval['action'] ?? '') !== 'nova_actions') {
            throw new ActionException('Deze goedkeuring bestaat niet.');
        }
        if (($approval['status'] ?? '') !== 'open') {
            throw new ActionException('Hier is al over beslist ('.($approval['status'] ?? '').').');
        }
        $label = $user->role->label();
        if (! $user->role->isManagement() && $label !== ($approval['role'] ?? '')) {
            throw new ActionException('Dit voorstel moet worden goedgekeurd door iemand met de rol '.($approval['role'] ?? '').'.');
        }

        $today = now()->toDateString();
        $now = now()->format('Y-m-d\TH:i');
        $state = new StateReader($user);
        $runPath = 'ai.runs/'.($approval['run_id'] ?? '');
        $run = json_decode((string) OsRow::query()->where('path', $runPath)->value('value'), true);
        $summary = [];
        $status = $approve ? 'approved' : 'rejected';

        if ($approve && ($approval['expires'] ?? '9') < $today) {
            $status = 'expired';
        } elseif ($approve) {
            $actions = new Actions($state, $today);
            try {
                $summary = $actions->execute($user, $approval['acties'] ?? [], $run['agent'] ?? null)['samenvatting'];
            } catch (ActionException $e) {
                $status = 'invalid';
                $summary = [$e->getMessage()];
            }
        }

        $approval = array_merge($approval, ['status' => $status, 'by' => $state->myName(), 'at' => $now]);
        $rows = ['ai.approvals/'.$approvalId => $approval];
        if (is_array($run)) {
            $run['status'] = match ($status) {
                'approved' => 'completed', 'rejected' => 'cancelled', default => 'failed'
            };
            $run['wacht'] = '';
            $run['next_action'] = null;
            $run['last'] = $now;
            $run['resultaat'] = match ($status) {
                'approved' => 'Goedgekeurd door '.$state->myName().' en uitgevoerd: '.implode('; ', $summary),
                'rejected' => 'Afgewezen door '.$state->myName(),
                'expired' => 'Goedkeuring verlopen',
                default => 'Kon niet worden uitgevoerd: '.implode('; ', $summary),
            };
            $run['steps'][] = ['ts' => $now, 't' => $run['resultaat']];
            $rows[$runPath] = $run;
        }
        $eventId = 'evt_'.substr(bin2hex(random_bytes(4)), 0, 7);
        $rows['ai.audit/'.$eventId] = [
            'event_id' => $eventId, 'organization_id' => 'org_renovion', 'type' => 'approval.'.($status === 'approved' ? 'granted' : $status),
            'schema_version' => 1, 'occurred_at' => $now, 'actor' => 'user:'.$user->person_id, 'subject_id' => $approval['pid'] ?? '', 'correlation_id' => 'corr_'.($approval['pid'] ?? ''),
            'causation_id' => null, 'payload' => ['project_id' => $approval['pid'] ?? '', 'approval_id' => $approvalId, 'action' => 'nova_actions'],
        ];

        $rev = $this->write($user, $rows);

        return ['rev' => $rev, 'status' => $status, 'samenvatting' => $summary];
    }

    /** De agents schrijven als het systeem zelf: alle rechten, geen persoon. */
    public static function systemUser(): User
    {
        $user = new User(['name' => 'Renovion Groep', 'email' => 'systeem@renovion.nl', 'role' => UserRole::Admin, 'active' => true]);
        $user->person_id = null;

        return $user;
    }

    private function prompt(StateReader $state, array $signal, array $agent, array $skill, string $level, string $today): string
    {
        $levelText = match ($level) {
            'observeren' => 'Je niveau is "observeren": je signaleert en adviseert. Een voorstel wordt alleen als advies getoond, niet uitgevoerd.',
            'protocol', 'begrensd' => 'Je niveau is "'.$level.'": interne acties (taak, taak bijwerken, notitie, chatbericht, interne afspraak) voert de app direct uit. Berichten aan klanten, mails, planning en prijzen wachten altijd op goedkeuring van een mens.',
            default => 'Je niveau is "voorbereiden": je bereidt voor, een mens met de rol '.($skill['appr'] ?? $agent['rol']).' beslist.',
        };

        $cal = StateReader::calendar($today);

        return <<<TXT
Je bent {$agent['naam']}, de agent voor {$agent['spec']} in het bedrijfssysteem van Renovion Groep (renovatiebedrijf in Arnhem). Je vak: {$agent['taak']}. Je skill nu: {$skill['naam']}. Vandaag is {$today}. Kalender: {$cal}.

{$levelText}

Werkwijze:
- Kijk eerst in de gegevens (dossier, overzicht, zoeken). Verzin niets: geen namen, bedragen, telefoonnummers of id's die je niet hebt gezien.
- Doe hooguit één voorstel met de concrete acties die het signaal oplossen, of geen voorstel als er niets hoeft te gebeuren (bijvoorbeeld omdat het al geregeld is).
- Geef taken altijd een eigenaar (een medewerker-id met de juiste rol) en een deadline.
- Een bericht aan een klant schrijf je kort, vriendelijk en in de u-vorm, namens Renovion Groep BV, zonder interne gegevens zoals marges of kostprijzen. Prijzen in een begroting baseer je op de prijslijst; staat het artikel er niet in, zeg dat in gevolgen in plaats van een prijs te verzinnen.
- Sluit af met één of twee zinnen voor het logboek: wat je zag en wat je deed of voorstelt.
- Schrijf in het Nederlands, bedragen als € 1.250,00, zonder middelpunt (·) en zonder lange gedachtestreepjes.
TXT;
    }

    private function ownerOf(StateReader $state, string $role, ?string $pid): ?string
    {
        if (in_array($role, ['Projectleider', 'Uitvoerder'], true) && $pid && ($leader = $state->project($pid)['leider'] ?? null)) {
            return $leader;
        }
        foreach ([$role, 'Directie', 'Superadmin'] as $r) {
            foreach ($state->list('emps') as $e) {
                if (($e['sysrol'] ?? '') === $r && ($e['status'] ?? 'actief') !== 'gedeactiveerd') {
                    return $e['id'];
                }
            }
        }

        return null;
    }

    private function event(string $type, array $signal, string $runId, string $actor, string $now, array $extra = []): array
    {
        return [
            'event_id' => 'evt_'.substr(bin2hex(random_bytes(4)), 0, 7), 'organization_id' => 'org_renovion', 'type' => $type, 'schema_version' => 1, 'occurred_at' => $now,
            'actor' => $actor, 'subject_id' => $signal['pid'] ?? '', 'correlation_id' => 'corr_'.($signal['pid'] ?? ''), 'causation_id' => null,
            'payload' => ['project_id' => $signal['pid'], 'run_id' => $runId, 'agent' => $signal['agent'], 'skill' => $signal['skill'], ...$extra],
        ];
    }

    /** Schrijf losse regels en zet nieuwe elementen vooraan in hun volgorde. */
    private function write(User $user, array $rows): int
    {
        $changes = [];
        $orders = [];
        foreach ($rows as $path => $value) {
            $changes[] = [$path, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
            [$collection, $key] = explode('/', $path, 2);
            if (! OsRow::query()->where('path', $path)->whereNotNull('value')->exists()) {
                $orders[$collection][] = $key;
            }
        }
        foreach ($orders as $collection => $keys) {
            $order = (array) json_decode((string) OsRow::query()->where('path', $collection.'/#order')->value('value'), true);
            $changes[] = [$collection.'/#order', json_encode(array_values(array_unique([...array_reverse($keys), ...$order])))];
        }

        return app(StateStore::class)->apply($user, $changes)['rev'];
    }
}
