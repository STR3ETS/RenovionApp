<?php

namespace App\Os\Ai;

use App\Models\OsRow;
use App\Models\User;
use App\Os\StateStore;

/**
 * Wat Nova en de agents mogen voorstellen, en hoe het wordt uitgevoerd.
 *
 * Een voorstel is een lijst acties ({type, ...velden}). Bij het voorstellen
 * controleert check() of alles klopt (bestaat het project, de persoon, de
 * regel?); pas na bevestiging voert execute() het uit, als de gebruiker die
 * bevestigde. Alles gaat via StateStore, dus met dezelfde rechten en
 * hetzelfde logboek als een handeling in het scherm.
 */
final class Actions
{
    public const TYPES = ['taak', 'taak_bijwerken', 'afspraak', 'notitie', 'contactmoment', 'chatbericht', 'klantbericht', 'mail', 'inplannen', 'planning_wijzigen', 'begrotingsregel', 'aanvraag'];

    /** Interne acties die Nova of een agent zelf mag uitvoeren als de instelling dat toestaat. */
    public const SAFE = ['taak', 'taak_bijwerken', 'notitie', 'contactmoment', 'chatbericht', 'afspraak'];

    /** @var array<string, string> pad => json, wat er bij uitvoeren wordt opgeslagen */
    private array $changes = [];

    /** @var array<string, mixed> wat in deze ronde al gelezen of gewijzigd is */
    private array $cache = [];

    /** Eigenaar van een taak als het voorstel er geen noemt (bij agents: de eigenaar van de skill). */
    public ?string $defaultOwner = null;

    /** Het project dat de laatste aanvraag-actie maakte (voor de website-ingang). */
    public ?string $lastProjectId = null;

    public function __construct(private readonly StateReader $s, private readonly string $today) {}

    /** Het schema van het voorstel-gereedschap. */
    public static function toolDefinition(string $name = 'voorstel', string $extra = ''): array
    {
        $date = ['type' => 'string', 'description' => 'JJJJ-MM-DD'];

        return [
            'name' => $name,
            'description' => 'Leg een voorstel voor aan de gebruiker. Het wordt pas uitgevoerd als een mens op Bevestigen drukt. Gebruik alleen id\'s die je met zoeken, dossier of overzicht hebt gevonden.'.$extra,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'begrepen' => ['type' => 'string', 'description' => 'In één zin wat je hebt begrepen dat er moet gebeuren.'],
                    'gebruikte_gegevens' => ['type' => 'string', 'description' => 'Kort welke gegevens je gebruikte (dossier, klant, datum).'],
                    'gevolgen' => ['type' => 'string', 'description' => 'Wat de gebruiker moet weten: ontbrekende gegevens, risico, wie het merkt. Leeg als er niets bijzonders is.'],
                    'acties' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 12,
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'type' => ['type' => 'string', 'enum' => self::TYPES, 'description' => 'taak: nieuwe taak. taak_bijwerken: status, eigenaar, deadline of prioriteit van een taak. afspraak: in de agenda. notitie: aantekening in het dossier. contactmoment: gebeld, mail, WhatsApp of voicemail vastleggen. chatbericht: bericht in de teamchat. klantbericht: bericht aan de klant in het klantportaal. mail: e-mail aan iemand (komt in de outbox). inplannen: medewerker op een project inplannen. planning_wijzigen: planningsblok verplaatsen. begrotingsregel: prijs invullen voor een begrotingsregel. aanvraag: nieuwe klant en aanvraag.'],
                                'titel' => ['type' => 'string'],
                                'tekst' => ['type' => 'string', 'description' => 'Inhoud van notitie, bericht of mail, of omschrijving van een taak.'],
                                'project_id' => ['type' => 'string'],
                                'taak_id' => ['type' => 'string'],
                                'blok_id' => ['type' => 'string'],
                                'regel_id' => ['type' => 'string'],
                                'eigenaar' => ['type' => 'string', 'description' => 'Id van een medewerker'],
                                'medewerker' => ['type' => 'string', 'description' => 'Id van een medewerker'],
                                'deelnemers' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Id\'s van medewerkers'],
                                'deadline' => $date,
                                'datum' => $date,
                                'start' => $date,
                                'eind' => $date,
                                'tijd' => ['type' => 'string', 'description' => 'UU:MM'],
                                'duur_minuten' => ['type' => 'integer'],
                                'soort' => ['type' => 'string', 'description' => 'afspraak: afspraak, terug, intake of overleg. contactmoment: gebeld, geen (geen gehoor), voicemail, mail of wa. aanvraag: Particulier of Zakelijk.'],
                                'status' => ['type' => 'string', 'enum' => ['open', 'todo', 'bezig', 'wacht', 'afgerond']],
                                'prioriteit' => ['type' => 'string', 'enum' => ['laag', 'normaal', 'hoog']],
                                'aan' => ['type' => 'string', 'description' => 'E-mailadres'],
                                'onderwerp' => ['type' => 'string'],
                                'uren_per_eenheid' => ['type' => 'number'],
                                'materiaal_per_eenheid' => ['type' => 'number'],
                                'materieel_per_eenheid' => ['type' => 'number'],
                                'onderaanneming_per_eenheid' => ['type' => 'number'],
                                'vast_bedrag' => ['type' => 'number'],
                                'naam' => ['type' => 'string', 'description' => 'Aanvraag: naam van de klant (Familie ... of bedrijfsnaam)'],
                                'contactpersoon' => ['type' => 'string'],
                                'telefoon' => ['type' => 'string'],
                                'mailadres' => ['type' => 'string'],
                                'adres' => ['type' => 'string'],
                                'plaats' => ['type' => 'string'],
                                'soort_werk' => ['type' => 'string', 'description' => 'Badkamerrenovatie, Keukenrenovatie, Aanbouw, Dakkapel, Renovatie, Verbouwing, Nieuwbouw, Onderhoud, Stucwerk, Schilderwerk of Tegelwerk'],
                                'budget' => ['type' => 'number'],
                            ],
                            'required' => ['type'],
                        ],
                    ],
                ],
                'required' => ['begrepen', 'acties'],
            ],
        ];
    }

    /**
     * Controleer een voorstel. Geeft per actie een leesbare regel terug, of
     * gooit een uitleg die terug naar het model gaat zodat het kan verbeteren.
     *
     * @param  list<array<string, mixed>>  $actions
     * @return list<string>
     */
    public function check(array $actions): array
    {
        $lines = [];
        foreach (array_values($actions) as $i => $a) {
            $error = $this->problem($a);
            if ($error) {
                throw new ActionException('Actie '.($i + 1).' ('.($a['type'] ?? '?').'): '.$error);
            }
            $lines[] = $this->describe($a);
        }

        return $lines;
    }

    public static function isSafe(array $actions): bool
    {
        foreach ($actions as $a) {
            if (! in_array($a['type'] ?? '', self::SAFE, true)) {
                return false;
            }
        }

        return true;
    }

    private function problem(array $a): ?string
    {
        $type = $a['type'] ?? '';
        $need = fn (string ...$fields) => ($missing = array_values(array_filter($fields, fn ($f) => ! isset($a[$f]) || $a[$f] === '' || $a[$f] === []))) ? 'ontbreekt: '.implode(', ', $missing) : null;
        $date = fn (?string $d) => $d === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        $project = fn (bool $required) => isset($a['project_id']) && $a['project_id'] !== ''
            ? ($this->s->project((string) $a['project_id']) ? null : 'project '.$a['project_id'].' bestaat niet')
            : ($required ? 'ontbreekt: project_id' : null);
        $person = fn (?string $id) => $id === null || $id === '' || $this->s->person($id) ? null : "medewerker {$id} bestaat niet";

        return match ($type) {
            'taak' => $need('titel') ?? $project(false) ?? $person($a['eigenaar'] ?? null) ?? ($date($a['deadline'] ?? null) ? null : 'deadline moet JJJJ-MM-DD zijn'),
            'taak_bijwerken' => $need('taak_id') ?? ($this->s->find('tasks', 'id', $a['taak_id']) ? null : 'taak '.$a['taak_id'].' bestaat niet') ?? $person($a['eigenaar'] ?? null) ?? ($date($a['deadline'] ?? null) ? null : 'deadline moet JJJJ-MM-DD zijn'),
            'afspraak' => $need('titel', 'datum', 'tijd') ?? $project(false) ?? ($date($a['datum']) ? null : 'datum moet JJJJ-MM-DD zijn') ?? (preg_match('/^\d{2}:\d{2}$/', (string) $a['tijd']) ? null : 'tijd moet UU:MM zijn') ?? $this->people($a['deelnemers'] ?? []),
            'notitie' => $need('tekst') ?? $project(true),
            'contactmoment' => $need('soort') ?? $project(true) ?? (in_array($a['soort'], ['gebeld', 'geen', 'voicemail', 'mail', 'wa', 'terug'], true) ? null : 'soort moet gebeld, geen, voicemail, mail of wa zijn'),
            'chatbericht' => $need('tekst') ?? $project(false),
            'klantbericht' => $need('tekst') ?? $project(true),
            'mail' => $need('aan', 'onderwerp', 'tekst') ?? (filter_var($a['aan'], FILTER_VALIDATE_EMAIL) ? null : 'aan is geen geldig e-mailadres') ?? $project(false),
            'inplannen' => $need('medewerker', 'titel', 'start', 'eind') ?? $person($a['medewerker']) ?? $project(false) ?? ($date($a['start']) && $date($a['eind']) && $a['start'] <= $a['eind'] ? null : 'start en eind moeten JJJJ-MM-DD zijn, start voor eind'),
            'planning_wijzigen' => $need('blok_id') ?? ($this->s->find('blocks', 'id', $a['blok_id']) ? null : 'planningsblok '.$a['blok_id'].' bestaat niet') ?? $person($a['medewerker'] ?? null) ?? ($date($a['start'] ?? null) && $date($a['eind'] ?? null) ? null : 'start en eind moeten JJJJ-MM-DD zijn'),
            'begrotingsregel' => $need('regel_id') ?? $project(true) ?? ($this->findLine($a['project_id'], $a['regel_id']) ? null : 'regel '.$a['regel_id'].' staat niet in de begroting van '.$a['project_id']) ?? ($this->hasPrice($a) ? null : 'geef minstens een van uren_per_eenheid, materiaal_per_eenheid, materieel_per_eenheid, onderaanneming_per_eenheid of vast_bedrag'),
            'aanvraag' => $need('naam') ?? (empty($a['mailadres']) || filter_var($a['mailadres'], FILTER_VALIDATE_EMAIL) ? null : 'mailadres is ongeldig'),
            default => 'onbekend type',
        };
    }

    private function people(array $ids): ?string
    {
        foreach ($ids as $id) {
            if (! $this->s->person((string) $id)) {
                return "medewerker {$id} bestaat niet";
            }
        }

        return null;
    }

    private function hasPrice(array $a): bool
    {
        foreach (['uren_per_eenheid', 'materiaal_per_eenheid', 'materieel_per_eenheid', 'onderaanneming_per_eenheid', 'vast_bedrag'] as $f) {
            if (isset($a[$f]) && is_numeric($a[$f])) {
                return true;
            }
        }

        return false;
    }

    private function findLine(string $pid, string $lineId): ?array
    {
        $b = $this->s->project($pid)['begroting'] ?? null;
        foreach ($b['chapters'] ?? [] as $ch) {
            foreach ($ch['pars'] ?? [] as $par) {
                foreach ($par['lines'] ?? [] as $line) {
                    if (($line['id'] ?? null) === $lineId) {
                        return $line;
                    }
                }
            }
        }

        return null;
    }

    /** Eén leesbare regel per actie, voor de voorstelkaart. */
    public function describe(array $a): string
    {
        $p = fn () => isset($a['project_id']) && $a['project_id'] ? ' ('.($this->s->project($a['project_id'])['naam'] ?? $a['project_id']).')' : '';
        $who = fn (?string $id) => $id ? $this->s->personName($id) : 'jou';
        $date = fn (?string $d) => $d ? date('j-n-Y', strtotime($d)) : '';

        return match ($a['type']) {
            'taak' => 'Taak "'.$a['titel'].'" voor '.$who($a['eigenaar'] ?? null).($a['deadline'] ?? null ? ', deadline '.$date($a['deadline']) : '').(($a['prioriteit'] ?? '') === 'hoog' ? ', prioriteit hoog' : '').$p(),
            'taak_bijwerken' => 'Taak "'.($this->s->find('tasks', 'id', $a['taak_id'])['titel'] ?? $a['taak_id']).'" bijwerken: '.implode(', ', array_filter([
                isset($a['status']) ? 'status '.$a['status'] : null, isset($a['eigenaar']) ? 'eigenaar '.$who($a['eigenaar']) : null,
                isset($a['deadline']) ? 'deadline '.$date($a['deadline']) : null, isset($a['prioriteit']) ? 'prioriteit '.$a['prioriteit'] : null,
            ])),
            'afspraak' => 'Afspraak "'.$a['titel'].'" op '.$date($a['datum']).' om '.$a['tijd'].(($a['deelnemers'] ?? []) ? ' met '.implode(', ', array_map(fn ($id) => $this->s->personName($id), $a['deelnemers'])) : '').$p(),
            'notitie' => 'Notitie in het dossier'.$p().': "'.$a['tekst'].'"',
            'contactmoment' => 'Contactmoment vastleggen'.$p().': '.$a['soort'].($a['tekst'] ?? '' ? ', "'.$a['tekst'].'"' : ''),
            'chatbericht' => 'Bericht in de teamchat'.$p().': "'.$a['tekst'].'"',
            'klantbericht' => 'Bericht aan de klant in het portaal'.$p().': "'.$a['tekst'].'"',
            'mail' => 'Mail aan '.$a['aan'].': "'.$a['onderwerp'].'" (komt in de outbox)',
            'inplannen' => $who($a['medewerker']).' inplannen op "'.$a['titel'].'" van '.$date($a['start']).' t/m '.$date($a['eind']).$p(),
            'planning_wijzigen' => 'Planningsblok "'.($this->s->find('blocks', 'id', $a['blok_id'])['titel'] ?? $a['blok_id']).'" '.implode(', ', array_filter([
                isset($a['medewerker']) ? 'naar '.$who($a['medewerker']) : null, isset($a['start']) ? 'vanaf '.$date($a['start']) : null, isset($a['eind']) ? 't/m '.$date($a['eind']) : null,
            ])),
            'begrotingsregel' => 'Prijs voor "'.($this->findLine($a['project_id'], $a['regel_id'])['d'] ?? $a['regel_id']).'"'.$p().': '.implode(', ', array_filter([
                isset($a['uren_per_eenheid']) ? $a['uren_per_eenheid'].' uur per eenheid' : null,
                isset($a['materiaal_per_eenheid']) ? StateReader::euro((float) $a['materiaal_per_eenheid']).' materiaal' : null,
                isset($a['materieel_per_eenheid']) ? StateReader::euro((float) $a['materieel_per_eenheid']).' materieel' : null,
                isset($a['onderaanneming_per_eenheid']) ? StateReader::euro((float) $a['onderaanneming_per_eenheid']).' onderaanneming' : null,
                isset($a['vast_bedrag']) ? StateReader::euro((float) $a['vast_bedrag']).' vast' : null,
            ])),
            'aanvraag' => 'Nieuwe aanvraag en klant "'.$a['naam'].'"'.(($a['soort_werk'] ?? '') ? ', '.$a['soort_werk'] : '').(($a['plaats'] ?? '') ? ' in '.$a['plaats'] : ''),
            default => $a['type'],
        };
    }

    /**
     * Voer de acties uit als $user. $agent is de agent die het voorstelde
     * (voor de chat en het logboek), of null voor Nova op verzoek.
     *
     * @param  list<array<string, mixed>>  $actions
     * @return array{rev: int, rejected: list<string>, samenvatting: list<string>}
     */
    public function execute(User $user, array $actions, ?string $agent = null): array
    {
        $this->check($actions);
        $this->changes = [];
        $this->cache = [];
        $actor = $agent ? ucfirst($agent).' (agent)' : $this->s->myName();
        $done = [];

        foreach ($actions as $a) {
            $this->apply($a, $actor, $agent);
            $done[] = $this->describe($a);
        }

        $result = app(StateStore::class)->apply($user, array_map(fn ($path, $json) => [$path, $json], array_keys($this->changes), $this->changes));

        return ['rev' => $result['rev'], 'rejected' => $result['rejected'], 'samenvatting' => $done];
    }

    private function apply(array $a, string $actor, ?string $agent): void
    {
        $pid = isset($a['project_id']) && $a['project_id'] ? strtoupper((string) $a['project_id']) : '';
        $now = now()->format('H:i');
        $note = fn (string $text, string $kind = 'blue') => $pid ? $this->update('projects/'.$pid, function (array $p) use ($text, $kind) {
            array_unshift($p['activity'], ['d' => $this->today, 't' => $text, 'k' => $kind]);

            return $p;
        }) : null;

        switch ($a['type']) {
            case 'taak':
                $this->add('tasks', ['id' => $this->id('t'), 'titel' => $a['titel'], 'pid' => $pid, 'eig' => $a['eigenaar'] ?? $this->defaultOwner ?? $this->s->user->person_id, 'dl' => $a['deadline'] ?? date('Y-m-d', strtotime($this->today.' +1 day')), 'status' => 'todo', 'prio' => $a['prioriteit'] ?? 'normaal', 'oms' => trim(($a['tekst'] ?? '').($agent ? "\nVoorgesteld door ".ucfirst($agent).'.' : '')), 'checklist' => []]);
                $note('Taak aangemaakt: '.$a['titel']);
                break;
            case 'taak_bijwerken':
                $this->update('tasks/'.$a['taak_id'], fn (array $t) => array_merge($t, array_filter([
                    'status' => $a['status'] ?? null, 'eig' => $a['eigenaar'] ?? null, 'dl' => $a['deadline'] ?? null, 'prio' => $a['prioriteit'] ?? null,
                ])));
                break;
            case 'afspraak':
                $soort = in_array($a['soort'] ?? '', ['afspraak', 'terug', 'intake', 'overleg'], true) ? $a['soort'] : 'afspraak';
                $this->add('agenda', ['id' => $this->id('a'), 'd' => $a['datum'], 'tijd' => $a['tijd'], 'titel' => $a['titel'], 'sub' => $pid ? ($this->s->project($pid)['naam'] ?? '') : '', 'pid' => $pid, 'wie' => array_values($a['deelnemers'] ?? array_filter([$this->s->user->person_id])), 'type' => $soort, 'dur' => (int) ($a['duur_minuten'] ?? ($soort === 'terug' ? 15 : 60))]);
                $note('Afspraak gepland: '.$a['titel'].' op '.date('j-n', strtotime($a['datum'])).' '.$a['tijd']);
                break;
            case 'notitie':
                $note($a['tekst'].' ('.$actor.')');
                break;
            case 'contactmoment':
                $this->updateMap('contacts', $pid, function (array $list) use ($a, $now) {
                    $list[] = ['id' => $this->id('c'), 'd' => $this->today, 't' => $now, 'type' => $a['soort'], 'by' => $this->s->user->person_id, 'note' => (string) ($a['tekst'] ?? '')];

                    return $list;
                });
                $note('Contactmoment: '.$a['soort'].(($a['tekst'] ?? '') ? ', '.$a['tekst'] : ''));
                break;
            case 'chatbericht':
                $channel = 'algemeen';
                foreach ($this->s->lists['chat.channels'] ?? [] as $ch) {
                    if ($pid && ($ch['pid'] ?? '') === $pid) {
                        $channel = $ch['id'];
                    }
                }
                $this->add('chat.msgs', ['id' => $this->id('m'), 'ch' => $channel, 'van' => $agent ? 'nova' : ($this->s->user->person_id ?: 'nova'), 'agent' => $agent, 'txt' => $a['tekst'], 'd' => $this->today, 'tijd' => $now, 'pinned' => false, 'replies' => [], 'att' => null], false);
                break;
            case 'klantbericht':
                $this->add('klantBerichten', ['id' => $this->id('kb'), 'pid' => $pid, 'van' => 'renovion', 'naam' => $this->s->myName(), 'txt' => $a['tekst'], 'd' => $this->today, 'tijd' => $now], false);
                $note('Bericht aan klant via het portaal'.($agent ? ' (voorgesteld door '.ucfirst($agent).')' : ''));
                break;
            case 'mail':
                $this->add('outbox', ['d' => $this->today, 'aan' => $a['aan'], 'onderwerp' => $a['onderwerp'], 'tekst' => $a['tekst'], 'pid' => $pid, 'door' => $actor], false);
                $note('Mail klaargezet aan '.$a['aan'].': '.$a['onderwerp']);
                break;
            case 'inplannen':
                $this->add('blocks', ['id' => $this->id('b'), 'emp' => $a['medewerker'], 'pid' => $pid, 'titel' => $a['titel'], 'start' => $a['start'], 'end' => $a['eind']], false);
                $note($this->s->personName($a['medewerker']).' ingepland: '.$a['titel']);
                break;
            case 'planning_wijzigen':
                $this->update('blocks/'.$a['blok_id'], fn (array $b) => array_merge($b, array_filter(['emp' => $a['medewerker'] ?? null, 'start' => $a['start'] ?? null, 'end' => $a['eind'] ?? null])));
                break;
            case 'begrotingsregel':
                $this->update('projects/'.$pid, function (array $p) use ($a) {
                    foreach ($p['begroting']['chapters'] as &$ch) {
                        foreach ($ch['pars'] as &$par) {
                            foreach ($par['lines'] as &$line) {
                                if (($line['id'] ?? null) === $a['regel_id']) {
                                    foreach (['uren_per_eenheid' => 'n', 'materiaal_per_eenheid' => 'm', 'materieel_per_eenheid' => 'e', 'onderaanneming_per_eenheid' => 'o', 'vast_bedrag' => 'v'] as $from => $to) {
                                        if (isset($a[$from]) && is_numeric($a[$from])) {
                                            $line[$to] = (float) $a[$from];
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $p['begroting']['gewijzigd'] = $this->today;

                    return $p;
                });
                $note('Prijs ingevuld voor begrotingsregel ('.$actor.')', 'vio');
                break;
            case 'aanvraag':
                $this->createLead($a, $actor);
                break;
        }
    }

    private function createLead(array $a, string $actor): void
    {
        $seq = $this->whole('seq');
        $seq['pd'] = (int) ($seq['pd'] ?? 0) + 1;
        $this->setWhole('seq', $seq);
        $year = substr($this->today, 0, 4);
        $pid = 'PD-'.$year.'-'.str_pad((string) $seq['pd'], 4, '0', STR_PAD_LEFT);
        $this->lastProjectId = $pid;

        $numbers = array_map(fn ($c) => (int) preg_replace('/\D/', '', (string) ($c['nr'] ?? '')), $this->s->list('clients'));
        $zakelijk = ($a['soort'] ?? '') === 'Zakelijk';
        // Een bestaande klant (klant_id) krijgt er een aanvraag bij; anders een nieuwe klant.
        $clientId = isset($a['klant_id']) && $this->s->client($a['klant_id']) ? $a['klant_id'] : null;
        if (! $clientId) {
            $clientId = $this->id('c');
            $this->add('clients', ['id' => $clientId, 'naam' => $a['naam'], 'soort' => $zakelijk ? 'Zakelijk' : 'Particulier', 'contact' => $a['contactpersoon'] ?? $a['naam'], 'tel' => $a['telefoon'] ?? '', 'mail' => $a['mailadres'] ?? '', 'plaats' => $a['plaats'] ?? '', 'nr' => 'K-'.(max([1000, ...$numbers]) + 1), 'betaal' => $zakelijk ? 30 : 14, 'herinnering' => true, 'token' => bin2hex(random_bytes(4))], false);
        }

        $sales = null;
        foreach (['Sales', 'Directie', 'Superadmin'] as $role) {
            foreach ($this->s->list('emps') as $e) {
                if (($e['sysrol'] ?? '') === $role && ($e['status'] ?? 'actief') !== 'gedeactiveerd') {
                    $sales ??= $e['id'];
                }
            }
        }
        $type = $a['soort_werk'] ?? 'Renovatie';
        $short = preg_replace('/^Familie\s+/i', '', (string) $a['naam']);
        // Zoals het prototype: "Badkamerrenovatie" + "Familie de Boer" wordt "Badkamer de Boer"
        $label = trim((string) preg_replace('/\s*renovatie$/i', '', $type));
        $label = $label === '' || strcasecmp($label, 'Complete') === 0 ? 'Renovatie' : $label;
        $this->add('projects', [
            'id' => $pid, 'naam' => $label.' '.$short, 'clientId' => $clientId, 'adres' => $a['adres'] ?? '', 'plaats' => $a['plaats'] ?? '', 'type' => $type,
            'phase' => 0, 'leider' => $sales ?? $this->s->user->person_id, 'progress' => 0, 'planned' => 0, 'kleur' => count($this->s->list('projects')) % 6, 'waarde' => (float) ($a['budget'] ?? 0),
            'termijnen' => [['oms' => 'Bij opdracht', 'pct' => 40], ['oms' => 'Bij start uitvoering', 'pct' => 40], ['oms' => 'Bij oplevering', 'pct' => 20]],
            'begroting' => null, 'offerte' => null, 'docs' => [], 'activity' => [['d' => $this->today, 't' => ($a['activiteit'] ?? 'Aanvraag aangemaakt door '.$actor), 'k' => 'blue']], 'bron' => $a['bron'] ?? 'Nova',
            'intake' => array_filter(['wensen' => $a['tekst'] ?? '', 'budget' => isset($a['budget']) ? StateReader::euro((float) $a['budget']) : '', 'startWens' => $a['gewenste_start'] ?? null], fn ($v) => $v !== null) ?: ['wensen' => ''], 'kosten' => 0,
            'uren' => [], 'regieMat' => [], 'mw' => [], 'contract' => 'aangenomen', 'regieTarief' => 55, 'bouw' => ['gpi' => false, 'kantoor' => false, 'klus' => false, 'contact' => '', 'pakket' => null],
        ], true);
        $this->add('tasks', ['id' => $this->id('t'), 'titel' => 'Eerste contact opnemen', 'pid' => $pid, 'eig' => $sales ?? $this->s->user->person_id, 'dl' => date('Y-m-d', strtotime($this->today.' +1 day')), 'status' => 'open', 'prio' => 'hoog', 'oms' => 'Aangemaakt bij de aanvraag.', 'checklist' => []]);
    }

    /* ---------- regels lezen en schrijven ---------- */

    private function id(string $prefix): string
    {
        return $prefix.substr(bin2hex(random_bytes(4)), 0, 7);
    }

    /** @var array<string, mixed> de oorspronkelijke vorm (objecten), zodat {} geen [] wordt */
    private array $shapes = [];

    private function current(string $path): mixed
    {
        if (array_key_exists($path, $this->cache)) {
            return $this->cache[$path];
        }
        $json = OsRow::query()->where('path', $path)->value('value');
        $this->shapes[$path] = $json === null ? null : json_decode($json, false);

        return $this->cache[$path] = $json === null ? null : json_decode($json, true);
    }

    private function put(string $path, mixed $value): void
    {
        $this->cache[$path] = $value;
        $shaped = isset($this->shapes[$path]) ? self::shape($value, $this->shapes[$path]) : $value;
        $this->changes[$path] = json_encode($shaped, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Zet een gewijzigde waarde terug in de vorm van het origineel: wat een object was, blijft een object. */
    private static function shape(mixed $new, mixed $old): mixed
    {
        if ($old instanceof \stdClass && is_array($new)) {
            $out = new \stdClass;
            foreach ($new as $key => $value) {
                $out->{$key} = property_exists($old, (string) $key) ? self::shape($value, $old->{$key}) : $value;
            }

            return $out;
        }
        if (is_array($old) && is_array($new) && array_is_list($new)) {
            return array_map(fn ($value, $i) => array_key_exists($i, $old) ? self::shape($value, $old[$i]) : $value, $new, array_keys($new));
        }

        return $new;
    }

    private function add(string $collection, array $item, bool $front = true): void
    {
        $key = (string) ($item['id'] ?? $item['nr'] ?? $item['_k'] ?? '');
        if ($key === '') {
            $item['_k'] = $key = 'k'.bin2hex(random_bytes(4));
        }
        $this->put($collection.'/'.$key, $item);
        $order = (array) ($this->current($collection.'/#order') ?? []);
        $front ? array_unshift($order, $key) : $order[] = $key;
        $this->put($collection.'/#order', array_values(array_unique($order)));
    }

    private function update(string $path, callable $change): void
    {
        $value = $this->current($path);
        if (is_array($value)) {
            $value += ['activity' => []];
            if (! str_starts_with($path, 'projects/')) {
                unset($value['activity']);
            }
            $this->put($path, $change($value));
        }
    }

    private function updateMap(string $collection, string $key, callable $change): void
    {
        $this->put($collection.'/'.$key, $change((array) ($this->current($collection.'/'.$key) ?? [])));
    }

    private function whole(string $key): array
    {
        return (array) ($this->current($key) ?? []);
    }

    private function setWhole(string $key, array $value): void
    {
        $this->put($key, $value);
    }
}
