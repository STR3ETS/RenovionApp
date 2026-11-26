<?php

namespace App\Os\Ai;

/**
 * Wat er in de echte gegevens aandacht vraagt, per agent en skill. Dit zijn
 * vaste regels (geen AI): een agent komt pas in actie als hier iets staat.
 * Elk signaal heeft een sleutel, zodat hetzelfde punt niet steeds opnieuw
 * door een agent wordt opgepakt.
 */
final class Signals
{
    public function __construct(private readonly StateReader $s, private readonly string $today) {}

    /**
     * @return list<array{agent: string, skill: string, key: string, pid: ?string, tekst: string}>
     */
    public function all(): array
    {
        $out = [];
        $days = fn (?string $from) => $from ? (int) floor((strtotime($this->today) - strtotime($from)) / 86400) : 999;
        $name = fn (?string $pid) => ($this->s->project($pid)['naam'] ?? '') ?: 'intern';

        foreach ($this->s->list('projects') as $p) {
            if (($p['lost'] ?? false) || ($p['closed'] ?? false)) {
                continue;
            }
            $phase = (int) ($p['phase'] ?? 0);
            $last = $p['activity'][0]['d'] ?? null;
            $client = ($this->s->client($p['clientId'] ?? null) ?? []) + ['naam' => 'onbekende klant', 'tel' => ''];
            $p += ['naam' => $p['id']];

            // Lex: een aanvraag waar al een paar dagen niets mee gebeurt, of waar gegevens ontbreken
            if ($phase <= 1 && $days($last) >= 2) {
                $out[] = ['agent' => 'lex', 'skill' => 'complete_intake', 'key' => 'stil:'.$p['id'].':'.$last, 'pid' => $p['id'],
                    'tekst' => "Aanvraag {$p['id']} {$p['naam']} ({$client['naam']}) staat al {$days($last)} dagen stil in fase ".StateReader::phase($phase).(($client['tel'] ?? '') || ($client['telVast'] ?? '') ? '' : ', telefoonnummer ontbreekt').'.'];
            }
            // Lex: offerte verzonden zonder reactie
            $o = $p['offerte'] ?? null;
            if ($o && ($o['status'] ?? '') === 'verzonden' && $days($o['sent'] ?? null) >= 3) {
                $out[] = ['agent' => 'lex', 'skill' => 'complete_intake', 'key' => 'offerte:'.($o['nr'] ?? '').':'.($o['versie'] ?? 1), 'pid' => $p['id'],
                    'tekst' => 'Offerte '.($o['nr'] ?? '')." voor {$p['naam']} is {$days($o['sent'] ?? null)} dagen geleden verzonden en nog niet getekend (".count($o['views'] ?? []).' keer bekeken).'];
            }
            // Cato: begrotingsregels zonder prijs
            $budget = StateReader::budgetTotals($p['begroting'] ?? null);
            if ($budget['zonder_prijs'] && $phase <= 3) {
                $out[] = ['agent' => 'cato', 'skill' => 'propose_calculation_lines', 'key' => 'prijs:'.$p['id'].':'.count($budget['zonder_prijs']).':'.($p['begroting']['gewijzigd'] ?? ''), 'pid' => $p['id'],
                    'tekst' => 'Begroting '.($p['begroting']['nr'] ?? '')." van {$p['naam']} heeft ".count($budget['zonder_prijs']).' regels met een hoeveelheid maar zonder prijs.'];
            }
            // Liv: project in uitvoering waar de klant al een week niets heeft gehoord
            if ($phase === 6) {
                $msgs = array_filter($this->s->list('klantBerichten'), fn ($m) => ($m['pid'] ?? '') === $p['id'] && ($m['van'] ?? '') !== 'klant');
                $lastMsg = $msgs ? max(array_map(fn ($m) => $m['d'] ?? '', $msgs)) : null;
                if ($days($lastMsg) >= 7) {
                    $week = date('o-W', strtotime($this->today));
                    $out[] = ['agent' => 'liv', 'skill' => 'prepare_customer_update', 'key' => 'update:'.$p['id'].':'.$week, 'pid' => $p['id'],
                        'tekst' => "{$client['naam']} heeft ".($lastMsg ? "sinds {$lastMsg}" : 'nog nooit').' een update gehad over '.$p['naam'].' (voortgang '.($p['progress'] ?? 0).'%).'];
                }
            }
        }

        // Liv: klantvraag zonder antwoord van Renovion erna
        $byProject = [];
        foreach ($this->s->list('klantBerichten') as $m) {
            $byProject[$m['pid'] ?? ''][] = $m;
        }
        foreach ($byProject as $pid => $msgs) {
            usort($msgs, fn ($a, $b) => strcmp(($a['d'] ?? '').($a['tijd'] ?? ''), ($b['d'] ?? '').($b['tijd'] ?? '')));
            $lastMsg = end($msgs);
            if ($lastMsg && ($lastMsg['van'] ?? '') === 'klant') {
                $out[] = ['agent' => 'liv', 'skill' => 'triage_care_question', 'key' => 'vraag:'.($lastMsg['id'] ?? ''), 'pid' => $pid ?: null,
                    'tekst' => 'Klantvraag over '.$name($pid).' zonder antwoord ('.($lastMsg['d'] ?? '').'): "'.($lastMsg['txt'] ?? '').'"'];
            }
        }
        foreach ($this->s->list('service') as $m) {
            if (in_array($m['status'] ?? '', ['nieuw', 'open'], true)) {
                $out[] = ['agent' => 'liv', 'skill' => 'triage_care_question', 'key' => 'service:'.($m['id'] ?? ''), 'pid' => $m['pid'] ?? null,
                    'tekst' => 'Servicemelding '.($m['nr'] ?? '').' over '.$name($m['pid'] ?? null).': '.($m['titel'] ?? '').'. '.($m['oms'] ?? '')];
            }
        }

        // Nova: taken over de deadline
        foreach ($this->s->list('tasks') as $t) {
            if (($t['status'] ?? '') !== 'afgerond' && ($t['dl'] ?? '9') < $this->today) {
                $out[] = ['agent' => 'nova', 'skill' => 'send_internal_reminder', 'key' => 'deadline:'.($t['id'] ?? '').':'.($t['dl'] ?? ''), 'pid' => ($t['pid'] ?? '') ?: null,
                    'tekst' => 'Taak '.($t['id'] ?? '').' "'.($t['titel'] ?? '').'" van '.$this->s->personName($t['eig'] ?? null).' is over de deadline ('.($t['dl'] ?? '').').'];
            }
        }

        // Prep: materiaal dat op tijd besteld moet worden
        foreach ($this->s->list('tasks') as $t) {
            if (($t['status'] ?? '') !== 'afgerond' && preg_match('/bestel|inkoop|materia/i', (string) ($t['titel'] ?? '')) && ($t['dl'] ?? '9') <= date('Y-m-d', strtotime($this->today.' +4 days'))) {
                $out[] = ['agent' => 'prep', 'skill' => 'check_material_status', 'key' => 'mat:'.($t['id'] ?? ''), 'pid' => ($t['pid'] ?? '') ?: null,
                    'tekst' => 'Inkooptaak "'.($t['titel'] ?? '').'" voor '.$name($t['pid'] ?? null).' moet uiterlijk '.($t['dl'] ?? '').' klaar zijn.'];
            }
        }
        foreach ($this->s->list('pos') as $o) {
            if (($o['status'] ?? '') === 'concept' && ($o['levering'] ?? '') && $o['levering'] <= date('Y-m-d', strtotime($this->today.' +10 days'))) {
                $out[] = ['agent' => 'prep', 'skill' => 'check_material_status', 'key' => 'po:'.($o['nr'] ?? ''), 'pid' => $o['pid'] ?? null,
                    'tekst' => 'Inkooporder '.($o['nr'] ?? '').' bij '.($o['lev'] ?? '').' is nog niet besteld, levering nodig op '.$o['levering'].'.'];
            }
        }

        // Milo: dubbel ingeplande mensen, één signaal per persoon met alle blokken die overlappen
        $blocks = $this->s->list('blocks');
        $clash = [];
        foreach ($blocks as $i => $a) {
            foreach (array_slice($blocks, $i + 1) as $b) {
                if (($a['emp'] ?? '') === ($b['emp'] ?? '') && ($b['start'] ?? '') <= ($a['end'] ?? '') && ($a['start'] ?? '') <= ($b['end'] ?? '') && max($a['end'] ?? '', $b['end'] ?? '') >= $this->today) {
                    $clash[$a['emp']][$a['id']] = $a;
                    $clash[$a['emp']][$b['id']] = $b;
                }
            }
        }
        foreach ($clash as $emp => $involved) {
            ksort($involved);
            $out[] = ['agent' => 'milo', 'skill' => 'propose_schedule_change', 'pid' => reset($involved)['pid'] ?? null,
                'key' => 'conflict:'.$emp.':'.substr(sha1(json_encode(array_map(fn ($x) => [$x['id'], $x['start'] ?? '', $x['end'] ?? ''], $involved))), 0, 16),
                'tekst' => $this->s->personName($emp).' ('.$emp.') staat dubbel ingepland. Blokken die overlappen: '.implode('; ', array_map(fn ($x) => 'blok '.$x['id'].' "'.($x['titel'] ?? '').'" ('.$name($x['pid'] ?? null).', '.($x['start'] ?? '').' t/m '.($x['end'] ?? '').')', $involved)).'. Los dit op in één voorstel.'];
        }

        // Dex: werkbonnen die wachten op controle
        foreach ($this->s->list('werkbonnen') as $w) {
            if (($w['status'] ?? '') === 'controle') {
                $out[] = ['agent' => 'dex', 'skill' => 'check_work_package_readiness', 'key' => 'wb:'.($w['nr'] ?? ''), 'pid' => $w['pid'] ?? null,
                    'tekst' => 'Werkbon '.($w['nr'] ?? '').' "'.($w['titel'] ?? '').'" van '.$this->s->personName($w['emp'] ?? null).' wacht op controle ('.($w['uren'] ?? 0).' uur, '.count($w['fotos'] ?? []).' foto\'s).'];
            }
        }

        // Finn: vervallen facturen
        foreach ($this->s->list('invoices') as $i) {
            if (($i['status'] ?? '') === 'verzonden' && ($i['verval'] ?? '9') < $this->today) {
                $out[] = ['agent' => 'finn', 'skill' => 'budget_forecast', 'key' => 'factuur:'.($i['nr'] ?? '').':'.($i['verval'] ?? ''), 'pid' => $i['pid'] ?? null,
                    'tekst' => 'Factuur '.($i['nr'] ?? '').' ('.$name($i['pid'] ?? null).', '.StateReader::euro((float) ($i['bedrag'] ?? 0) * 1.21).' incl. btw) is vervallen sinds '.($i['verval'] ?? '').'.'];
            }
        }

        return $out;
    }

    /** @return list<string> korte regels voor Nova */
    public function lines(): array
    {
        return array_map(fn ($x) => $x['tekst'], $this->all());
    }
}
