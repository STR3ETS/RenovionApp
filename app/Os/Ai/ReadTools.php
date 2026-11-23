<?php

namespace App\Os\Ai;

/**
 * De leesfuncties die Nova en de agents gebruiken om in de echte gegevens te
 * kijken. Ze lezen alleen wat de gebruiker zelf ook mag zien en geven korte,
 * feitelijke antwoorden met de sleutels (id's) die een voorstel nodig heeft.
 */
final class ReadTools
{
    public const TOPICS = ['taken', 'agenda', 'planning', 'facturen', 'offertes', 'aanvragen', 'projecten', 'werkbonnen', 'medewerkers', 'klanten', 'klantvragen', 'servicemeldingen', 'inkoop', 'prijslijst', 'partners', 'signalen'];

    public function __construct(private readonly StateReader $s, private readonly string $today) {}

    /** @return list<array<string, mixed>> */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'zoeken',
                'description' => 'Zoek klanten, projecten (dossiers), medewerkers, partners en taken op naam, plaats, nummer of omschrijving. Geeft id\'s terug die je in andere functies en in voorstellen gebruikt. Gebruik dit voordat je een id noemt.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['zoekterm' => ['type' => 'string', 'description' => 'Bijvoorbeeld "Jansen", "badkamer Arnhem" of "PD-2026-0004".']],
                    'required' => ['zoekterm'],
                ],
            ],
            [
                'name' => 'dossier',
                'description' => 'Alles over één project: klant en contactgegevens, fase, voortgang, planning, begroting (met regels zonder prijs), offerte, termijnen, facturen, open taken, afspraken, planningsblokken, klantberichten en de laatste activiteit.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['project_id' => ['type' => 'string', 'description' => 'Bijvoorbeeld PD-2026-0001']],
                    'required' => ['project_id'],
                ],
            ],
            [
                'name' => 'overzicht',
                'description' => 'Een lijst uit het systeem. Onderwerpen: '.implode(', ', self::TOPICS).'. "signalen" geeft wat aandacht vraagt (te late taken, vervallen facturen, offertes zonder reactie, dubbel ingeplande mensen, regels zonder prijs).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'onderwerp' => ['type' => 'string', 'enum' => self::TOPICS],
                        'vanaf' => ['type' => 'string', 'description' => 'Datum JJJJ-MM-DD, voor agenda en planning'],
                        'tot' => ['type' => 'string', 'description' => 'Datum JJJJ-MM-DD, voor agenda en planning'],
                        'medewerker' => ['type' => 'string', 'description' => 'Id van een medewerker, om te filteren'],
                        'project_id' => ['type' => 'string', 'description' => 'Alleen dit project'],
                        'alleen_open' => ['type' => 'boolean', 'description' => 'Alleen wat nog openstaat'],
                    ],
                    'required' => ['onderwerp'],
                ],
            ],
        ];
    }

    public function run(string $name, array $input): string
    {
        return match ($name) {
            'zoeken' => $this->search((string) ($input['zoekterm'] ?? '')),
            'dossier' => $this->dossier((string) ($input['project_id'] ?? '')),
            'overzicht' => $this->overview($input),
            default => 'Onbekende functie.',
        };
    }

    private function search(string $term): string
    {
        $q = mb_strtolower(trim($term));
        if ($q === '') {
            return 'Geef een zoekterm.';
        }
        $hit = fn (array $fields) => str_contains(mb_strtolower(implode(' ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $fields))), $q);
        $out = [];

        foreach ($this->s->list('clients') as $c) {
            if ($hit([$c['naam'] ?? '', $c['contact'] ?? '', $c['plaats'] ?? '', $c['nr'] ?? '', $c['mail'] ?? ''])) {
                $out[] = "klant {$c['id']}: ".($c['naam'] ?? '').' ('.($c['contact'] ?? '').', '.($c['plaats'] ?? '').', tel '.($c['tel'] ?: 'onbekend').', '.($c['mail'] ?? '').')';
            }
        }
        foreach ($this->s->list('projects') as $p) {
            $client = $this->s->client($p['clientId'] ?? null);
            if ($hit([$p['id'], $p['naam'] ?? '', $p['plaats'] ?? '', $p['adres'] ?? '', $p['type'] ?? '', $client['naam'] ?? ''])) {
                $out[] = "project {$p['id']}: ".($p['naam'] ?? '').', '.($client['naam'] ?? '').', '.($p['plaats'] ?? '').', fase '.StateReader::phase($p['phase'] ?? 0);
            }
        }
        foreach ([...$this->s->list('emps'), ...$this->s->list('extraUsers')] as $e) {
            if ($hit([$e['naam'] ?? '', $e['rol'] ?? '', $e['sysrol'] ?? ''])) {
                $out[] = "medewerker {$e['id']}: ".($e['naam'] ?? '').', '.($e['rol'] ?? '').', rol in systeem '.($e['sysrol'] ?? '');
            }
        }
        foreach ($this->s->list('subs') as $sub) {
            if ($hit([$sub['naam'] ?? '', $sub['contact'] ?? '', $sub['vak'] ?? ''])) {
                $out[] = "partner {$sub['id']}: ".($sub['naam'] ?? '').', '.($sub['vak'] ?? '').', keurmerk '.($sub['keurmerk'] ?? '');
            }
        }
        foreach ($this->s->list('tasks') as $t) {
            if ($hit([$t['titel'] ?? '', $t['oms'] ?? ''])) {
                $out[] = "taak {$t['id']}: ".($t['titel'] ?? '').', '.($t['status'] ?? '').', eigenaar '.$this->s->personName($t['eig'] ?? null).', deadline '.($t['dl'] ?? 'geen');
            }
        }

        return $out ? implode("\n", array_slice($out, 0, 25)) : "Niets gevonden voor \"{$term}\".";
    }

    private function dossier(string $id): string
    {
        $p = $this->s->project($id);
        if (! $p) {
            return "Project {$id} bestaat niet of is niet zichtbaar voor deze gebruiker. Zoek eerst met zoeken.";
        }
        $c = $this->s->client($p['clientId'] ?? null) ?? [];
        $b = StateReader::budgetTotals($p['begroting'] ?? null);
        $o = $p['offerte'] ?? null;
        $lines = [
            "Project {$p['id']}: ".($p['naam'] ?? ''),
            'Klant: '.($c['naam'] ?? 'onbekend').' (klant-id '.($c['id'] ?? '-').'), contact '.($c['contact'] ?? '-').', tel '.(($c['tel'] ?? '') ?: 'ontbreekt').', mail '.(($c['mail'] ?? '') ?: 'ontbreekt').', soort '.($c['soort'] ?? '-'),
            'Adres: '.trim(($p['adres'] ?? '').', '.($p['plaats'] ?? ''), ', '),
            'Soort werk: '.($p['type'] ?? '-').'. Fase: '.StateReader::phase($p['phase'] ?? 0).'. Projectleider: '.$this->s->personName($p['leider'] ?? null).' ('.($p['leider'] ?? '-').')',
            'Voortgang '.($p['progress'] ?? 0).'%, volgens planning '.($p['planned'] ?? 0).'%. Start '.($p['start'] ?? '-').', oplevering '.($p['eind'] ?? '-'),
            'Intake: '.json_encode($p['intake'] ?? new \stdClass, JSON_UNESCAPED_UNICODE),
        ];
        if ($p['begroting'] ?? null) {
            $lines[] = 'Begroting '.($p['begroting']['nr'] ?? '').' ('.($p['begroting']['status'] ?? '').'): directe kosten '.StateReader::euro($b['direct']).', totaal excl. btw '.StateReader::euro($b['excl']).', '.$b['regels'].' regels.';
            if ($b['zonder_prijs']) {
                $lines[] = 'Regels met hoeveelheid maar zonder prijs: '.json_encode(array_slice($b['zonder_prijs'], 0, 25), JSON_UNESCAPED_UNICODE);
            }
        } else {
            $lines[] = 'Nog geen begroting.';
        }
        $lines[] = $o ? 'Offerte '.($o['nr'] ?? '').': status '.($o['status'] ?? '').', aanneemsom excl. btw '.StateReader::euro((float) ($o['basis'] ?? 0)).', verzonden '.($o['sent'] ?? '-').', getekend '.($o['accepted'] ?? '-') : 'Nog geen offerte.';
        $lines[] = 'Termijnen: '.implode(', ', array_map(fn ($t) => ($t['oms'] ?? '').' '.($t['pct'] ?? 0).'%', $p['termijnen'] ?? []));

        $invoices = array_filter($this->s->list('invoices'), fn ($i) => ($i['pid'] ?? '') === $p['id']);
        $lines[] = 'Facturen: '.($invoices ? implode('; ', array_map(fn ($i) => ($i['nr'] ?? '').' '.($i['oms'] ?? '').' '.StateReader::euro((float) ($i['bedrag'] ?? 0)).' excl. btw, '.($i['status'] ?? '').', vervalt '.($i['verval'] ?? '-'), $invoices)) : 'geen');
        $tasks = array_filter($this->s->list('tasks'), fn ($t) => ($t['pid'] ?? '') === $p['id'] && ($t['status'] ?? '') !== 'afgerond');
        $lines[] = 'Open taken: '.($tasks ? implode('; ', array_map(fn ($t) => ($t['id'] ?? '').' "'.($t['titel'] ?? '').'" '.$this->s->personName($t['eig'] ?? null).' deadline '.($t['dl'] ?? '-'), $tasks)) : 'geen');
        $agenda = array_filter($this->s->list('agenda'), fn ($a) => ($a['pid'] ?? '') === $p['id'] && ($a['d'] ?? '') >= $this->today);
        $lines[] = 'Komende afspraken: '.($agenda ? implode('; ', array_map(fn ($a) => ($a['d'] ?? '').' '.($a['tijd'] ?? '').' '.($a['titel'] ?? ''), $agenda)) : 'geen');
        $blocks = array_filter($this->s->list('blocks'), fn ($bl) => ($bl['pid'] ?? '') === $p['id']);
        $lines[] = 'Planning: '.($blocks ? implode('; ', array_map(fn ($bl) => ($bl['id'] ?? '').' '.($bl['titel'] ?? '').' '.$this->s->personName($bl['emp'] ?? null).' '.($bl['start'] ?? '').' t/m '.($bl['end'] ?? ''), $blocks)) : 'nog niets ingepland');
        $messages = array_slice(array_values(array_filter($this->s->list('klantBerichten'), fn ($m) => ($m['pid'] ?? '') === $p['id'])), -6);
        $lines[] = 'Laatste klantberichten: '.($messages ? implode(' | ', array_map(fn ($m) => (($m['van'] ?? '') === 'klant' ? 'klant' : ($m['naam'] ?? 'Renovion')).' '.($m['d'] ?? '').': '.($m['txt'] ?? ''), $messages)) : 'geen');
        $lines[] = 'Laatste activiteit: '.implode(' | ', array_map(fn ($a) => ($a['d'] ?? '').' '.($a['t'] ?? ''), array_slice($p['activity'] ?? [], 0, 10)));

        return implode("\n", $lines);
    }

    private function overview(array $in): string
    {
        $topic = (string) ($in['onderwerp'] ?? '');
        $from = (string) ($in['vanaf'] ?? $this->today);
        $to = (string) ($in['tot'] ?? date('Y-m-d', strtotime($this->today.' +14 days')));
        $emp = $in['medewerker'] ?? null;
        $pid = isset($in['project_id']) ? strtoupper((string) $in['project_id']) : null;
        $open = (bool) ($in['alleen_open'] ?? true);
        $forProject = fn ($x) => ! $pid || ($x['pid'] ?? '') === $pid;
        $pname = fn ($id) => ($this->s->project($id)['naam'] ?? '') ?: 'intern';
        $rows = [];

        switch ($topic) {
            case 'taken':
                foreach ($this->s->list('tasks') as $t) {
                    if ((! $open || ($t['status'] ?? '') !== 'afgerond') && $forProject($t) && (! $emp || ($t['eig'] ?? '') === $emp)) {
                        $rows[] = ($t['id'] ?? '').' "'.($t['titel'] ?? '').'", '.($t['status'] ?? '').', prio '.($t['prio'] ?? '').', eigenaar '.$this->s->personName($t['eig'] ?? null).', deadline '.($t['dl'] ?? 'geen').', project '.$pname($t['pid'] ?? '');
                    }
                }
                break;
            case 'agenda':
                foreach ($this->s->list('agenda') as $a) {
                    if (($a['d'] ?? '') >= $from && ($a['d'] ?? '') <= $to && $forProject($a) && (! $emp || in_array($emp, (array) ($a['wie'] ?? []), true))) {
                        $rows[] = ($a['d'] ?? '').' '.($a['tijd'] ?? '').' '.($a['titel'] ?? '').' ('.($a['type'] ?? 'afspraak').', met '.implode(', ', array_map([$this->s, 'personName'], (array) ($a['wie'] ?? []))).', project '.$pname($a['pid'] ?? '').')';
                    }
                }
                sort($rows);
                break;
            case 'planning':
                foreach ($this->s->list('blocks') as $bl) {
                    if (($bl['end'] ?? '') >= $from && ($bl['start'] ?? '') <= $to && $forProject($bl) && (! $emp || ($bl['emp'] ?? '') === $emp)) {
                        $rows[] = ($bl['id'] ?? '').': '.$this->s->personName($bl['emp'] ?? null).' ('.($bl['emp'] ?? '').') '.($bl['start'] ?? '').' t/m '.($bl['end'] ?? '').', '.($bl['titel'] ?? '').', project '.$pname($bl['pid'] ?? '');
                    }
                }
                $rows[] = 'Inplanbare mensen: '.implode(', ', array_map(fn ($e) => ($e['id'] ?? '').' '.($e['naam'] ?? '').' ('.($e['rol'] ?? '').', '.($e['cap'] ?? 40).' uur per week)', array_filter($this->s->list('emps'), fn ($e) => ($e['type'] ?? '') !== 'kantoor' && ($e['status'] ?? 'actief') !== 'gedeactiveerd')));
                break;
            case 'facturen':
                foreach ($this->s->list('invoices') as $i) {
                    if ((! $open || ($i['status'] ?? '') !== 'betaald') && $forProject($i)) {
                        $late = ($i['status'] ?? '') === 'verzonden' && ($i['verval'] ?? '9') < $this->today ? ' VERVALLEN' : '';
                        $rows[] = ($i['nr'] ?? '').' '.$pname($i['pid'] ?? '').', '.($i['oms'] ?? '').', '.StateReader::euro((float) ($i['bedrag'] ?? 0)).' excl. btw, '.($i['status'] ?? '').', vervalt '.($i['verval'] ?? '-').$late;
                    }
                }
                break;
            case 'offertes':
                foreach ($this->s->list('projects') as $p) {
                    $o = $p['offerte'] ?? null;
                    if ($o && $forProject($p)) {
                        $rows[] = ($o['nr'] ?? '').' '.$p['id'].' '.($p['naam'] ?? '').': '.($o['status'] ?? '').', '.StateReader::euro((float) ($o['basis'] ?? 0)).' excl. btw, verzonden '.($o['sent'] ?? '-').', '.count($o['views'] ?? []).' keer bekeken';
                    }
                }
                break;
            case 'aanvragen':
            case 'projecten':
                foreach ($this->s->list('projects') as $p) {
                    if ($topic === 'aanvragen' && ($p['phase'] ?? 0) > 3) {
                        continue;
                    }
                    $c = $this->s->client($p['clientId'] ?? null) ?? [];
                    $last = ($p['activity'][0]['d'] ?? '-');
                    $rows[] = $p['id'].' '.($p['naam'] ?? '').', '.($c['naam'] ?? '').', '.($p['plaats'] ?? '').', fase '.StateReader::phase($p['phase'] ?? 0).', voortgang '.($p['progress'] ?? 0).'% (plan '.($p['planned'] ?? 0).'%), leider '.$this->s->personName($p['leider'] ?? null).', laatste activiteit '.$last;
                }
                break;
            case 'werkbonnen':
                foreach ($this->s->list('werkbonnen') as $w) {
                    if ($forProject($w) && (! $emp || ($w['emp'] ?? '') === $emp)) {
                        $rows[] = ($w['nr'] ?? '').' '.$pname($w['pid'] ?? '').', '.($w['titel'] ?? '').', '.$this->s->personName($w['emp'] ?? null).', '.($w['status'] ?? '').', '.($w['uren'] ?? 0).' uur, '.($w['datum'] ?? '');
                    }
                }
                break;
            case 'medewerkers':
                foreach ($this->s->list('emps') as $e) {
                    $rows[] = ($e['id'] ?? '').' '.($e['naam'] ?? '').', vak '.($e['rol'] ?? '').', rol '.($e['sysrol'] ?? '').', '.($e['type'] ?? '').', '.($e['status'] ?? 'actief').', tel '.(($e['tel'] ?? '') ?: '-');
                }
                break;
            case 'klanten':
                foreach ($this->s->list('clients') as $c) {
                    $rows[] = ($c['id'] ?? '').' '.($c['nr'] ?? '').' '.($c['naam'] ?? '').', '.($c['soort'] ?? '').', '.($c['contact'] ?? '').', tel '.(($c['tel'] ?? '') ?: 'ontbreekt').', '.(($c['mail'] ?? '') ?: 'geen mail').', '.($c['plaats'] ?? '');
                }
                break;
            case 'klantvragen':
                foreach ($this->s->list('klantBerichten') as $m) {
                    if (($m['van'] ?? '') === 'klant' && $forProject($m)) {
                        $rows[] = ($m['id'] ?? '').' '.($m['d'] ?? '').' '.($m['tijd'] ?? '').' project '.($m['pid'] ?? '').' van '.($m['naam'] ?? 'klant').': '.($m['txt'] ?? '');
                    }
                }
                break;
            case 'servicemeldingen':
                foreach ($this->s->list('service') as $m) {
                    if ((! $open || ! in_array($m['status'] ?? '', ['opgelost', 'gesloten'], true)) && $forProject($m)) {
                        $rows[] = ($m['nr'] ?? $m['id'] ?? '').' project '.($m['pid'] ?? '').': '.($m['titel'] ?? '').', '.($m['oms'] ?? '').', status '.($m['status'] ?? '').', garantie '.json_encode($m['garantie'] ?? null);
                    }
                }
                break;
            case 'inkoop':
                foreach ($this->s->list('pos') as $o) {
                    if ($forProject($o)) {
                        $rows[] = 'Inkooporder '.($o['nr'] ?? '').' '.($o['lev'] ?? '').', project '.$pname($o['pid'] ?? '').', status '.($o['status'] ?? '').', levering '.($o['levering'] ?? '-');
                    }
                }
                foreach ($this->s->list('inkoop') as $i) {
                    if ($forProject($i)) {
                        $rows[] = 'Inkoopfactuur '.($i['nr'] ?? '').' '.($i['lev'] ?? '').', '.StateReader::euro((float) ($i['bedrag'] ?? 0)).', status '.($i['status'] ?? '').', vervalt '.($i['verval'] ?? '-');
                    }
                }
                break;
            case 'prijslijst':
                foreach ($this->s->list('prijslijst') as $x) {
                    if (($x['actief'] ?? true) !== false) {
                        $rows[] = ($x['nr'] ?? '').' '.($x['oms'] ?? '').' ('.($x['cat'] ?? '').'), '.StateReader::euro((float) ($x['prijs'] ?? 0)).' per '.($x['eenheid'] ?? '').', soort '.($x['soort'] ?? '').', norm '.($x['norm'] ?? 0).' uur';
                    }
                }
                break;
            case 'partners':
                foreach ($this->s->list('subs') as $sub) {
                    $rows[] = ($sub['id'] ?? '').' '.($sub['naam'] ?? '').', '.($sub['vak'] ?? '').', '.($sub['regio'] ?? '').', keurmerk '.($sub['keurmerk'] ?? '').', tarief '.($sub['tarief'] ?? 0).($sub['blocked'] ?? false ? ', GEBLOKKEERD' : '');
                }
                break;
            case 'signalen':
                return implode("\n", (new Signals($this->s, $this->today))->lines()) ?: 'Er staan geen signalen open.';
        }

        return $rows ? implode("\n", array_slice($rows, 0, 60)).(count($rows) > 60 ? "\n(en nog ".(count($rows) - 60).' meer)' : '') : 'Niets gevonden.';
    }
}
