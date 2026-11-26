<?php

namespace App\Os\Portal;

use App\Models\OsRow;
use App\Models\User;
use App\Os\Layout;

/**
 * Wat een klant ziet en mag in het klantportaal (Service Hub).
 *
 * Een klant ziet alleen zijn eigen project(en): het project zelf, de eigen
 * klantgegevens, facturen, afspraken, berichten, verzoeken, meldingen en de
 * foto's van de werkbonnen. Interne notities, marges van andere klanten,
 * planning, chat en financiële cijfers van het bedrijf komen nooit mee.
 *
 * Schrijven mag alleen wat het portaal nodig heeft: berichten en notities,
 * een melding, aanleveren, een afspraak bevestigen, akkoord op meerwerk en
 * het ondertekenen van de offerte. Al het andere houdt de server tegen.
 */
final class ClientScope
{
    /** Onderdelen als geheel die een klant mag lezen. */
    private const WHOLE_READ = ['v', 'seq', 'prefs', 'autoInit'];

    /** Lijsten per project: lezen als het project van de klant is. */
    private const BY_PROJECT = ['invoices', 'agenda', 'klantBerichten', 'klantVerzoeken', 'klantNotities', 'service', 'offVragen', 'werkbonnen'];

    /** Velden van een medewerker die een klant mag zien (naam en hoe hij bereikbaar is). */
    private const EMP_FIELDS = ['id', 'naam', 'rol', 'type', 'tel', 'mail', 'sysrol', 'status'];

    /** Soorten documenten uit het dossier die voor de klant zijn (KLANT_DOC in het portaal). */
    private const DOC_CATS = ['Offerte', 'Overeenkomst', 'Tekening', 'Keuze', 'Vergunning', 'Planning', 'Garantie', 'Klant'];

    /** @return array{person: ?string, pids: list<string>, cid: ?string} */
    public static function for(User $user): array
    {
        $person = $user->person_id;
        $row = $person ? OsRow::query()->whereIn('path', ['extraUsers/'.$person, 'emps/'.$person])->whereNotNull('value')->value('value') : null;
        $me = Json::decode($row);

        $pids = [];
        $scope = is_object($me) ? (string) ($me->scope ?? '') : '';
        if (str_starts_with($scope, 'PD-')) {
            $pids[] = $scope;
        }

        $cid = null;
        foreach ($pids as $pid) {
            $project = Json::decode(OsRow::query()->where('path', 'projects/'.$pid)->value('value'));
            if (is_object($project) && isset($project->clientId)) {
                $cid = (string) $project->clientId;
            }
        }

        // Meer projecten van dezelfde klant horen er ook bij.
        if ($cid) {
            OsRow::query()->where('collection', 'projects')->whereNotNull('value')->where('value', 'like', '%"clientId":"'.addcslashes($cid, '%_').'"%')
                ->pluck('value')->each(function ($json) use (&$pids, $cid) {
                    $p = Json::decode($json);
                    if (is_object($p) && ($p->clientId ?? null) === $cid && ! in_array($p->id, $pids, true)) {
                        $pids[] = (string) $p->id;
                    }
                });
        }

        return ['person' => $person, 'pids' => $pids, 'cid' => $cid];
    }

    public function read(array $scope, string $path, string $collection, string $value): ?string
    {
        $key = Layout::keyOf($path);

        if ($key === null) {
            return in_array($collection, self::WHOLE_READ, true) || preg_match('/^r\d+[a-z]*$/', $collection) ? $value : null;
        }

        if ($key === '#order') {
            return $this->filterOrder($scope, $collection, $value);
        }

        $item = Json::decode($value);

        return match (true) {
            $collection === 'projects' => in_array($key, $scope['pids'], true) ? Json::encode($this->project($item)) : null,
            $collection === 'clients' => $key === $scope['cid'] ? $value : null,
            $collection === 'extraUsers' => $key === $scope['person'] ? $value : null,
            $collection === 'emps' => Json::encode($this->employee($item)),
            $collection === 'kwal' => in_array($key, $scope['pids'], true) ? $value : null,
            in_array($collection, self::BY_PROJECT, true) => in_array((string) Json::get($item, 'pid'), $scope['pids'], true) ? $value : null,
            default => null,
        };
    }

    public function write(array $scope, string $path, ?string $old, ?string $new): bool|string
    {
        $collection = Layout::collectionOf($path);
        $key = Layout::keyOf($path);

        if ($key === null) {
            return $collection === 'seq' ? Json::maxMerge($old, $new) : false;
        }

        if ($key === '#order') {
            return Json::mergeOrder($old, $new, fn () => true);
        }

        if ($new === null) {
            return false; // een klant haalt niets weg
        }

        $item = Json::decode($new);
        $prev = Json::decode($old);
        $ownPid = fn ($x) => is_object($x) && in_array((string) ($x->pid ?? ''), $scope['pids'], true);

        return match ($collection) {
            // Iets nieuws bij het eigen project
            'klantBerichten' => $prev === null && $ownPid($item) && ($item->van ?? '') === 'klant',
            'service', 'offVragen', 'invoices', 'tasks' => $prev === null && $ownPid($item) && $this->harmlessNew($collection, $item),
            'notifs', 'outbox', 'ai.audit' => $prev === null,
            'klantNotities' => $ownPid($item) && ($prev === null || $ownPid($prev)),
            // Bestaand: alleen het ene veld dat het portaal verandert
            'klantVerzoeken' => $ownPid($prev) ? Json::encode(Json::onlyFields($prev, $item, ['status'])) : false,
            'agenda' => $ownPid($prev) ? Json::encode(Json::onlyFields($prev, $item, ['bevestigd'])) : false,
            'projects' => in_array($key, $scope['pids'], true) && is_object($prev) ? Json::encode($this->projectWrite($prev, $item)) : false,
            default => false,
        };
    }

    /** Wat een klant van zijn project mag zien: alles behalve interne notities en een planning die nog een voorstel is. */
    private function project(object $p): object
    {
        if (isset($p->begroting) && is_object($p->begroting) && isset($p->begroting->notities)) {
            $p->begroting->notities = (object) ['prive' => '', 'vragen' => []];
        }
        unset($p->intern, $p->risico, $p->planVoorstel);

        // Het logboek en de documentenmap van het dossier zijn intern (inkoop, gunningen, gespreksverslagen):
        // de klant krijgt alleen wat het portaal toont. Werkelijke kosten en de nacalculatie gaan nooit mee.
        unset($p->kosten, $p->nacalcFinal);
        foreach (['activity' => 'publicActivity', 'docs' => 'publicDoc'] as $list => $rule) {
            if (isset($p->{$list}) && is_array($p->{$list})) {
                $p->{$list} = array_values(array_filter($p->{$list}, [self::class, $rule]));
            }
        }

        return $p;
    }

    /** Een regel uit het logboek die de klant als update ziet: een mijlpaal, zonder bedragen of interne zaken. Zelfde regel als "Recente updates" in het portaal. */
    public static function publicActivity(mixed $entry): bool
    {
        $text = is_object($entry) ? (string) ($entry->t ?? '') : '';

        return preg_match('/gestart|opgeleverd|klaar|gereed|ingepland|ondertekend|goedgekeurd|akkoord/iu', $text) === 1
            && preg_match('/€|marge|kosten|factuur|gegund|budget|begroting|calculatie|sjabloon|prijsaanvraag|planningsvoorstel|inkoop|intern|verslag|declaratie|herinnering|mailpakket|outbox|concept|nova|portaal|service hub/iu', $text) !== 1;
    }

    /** Een document dat voor de klant bedoeld is (offerte, overeenkomst, tekening …) of dat hij zelf aanleverde. */
    public static function publicDoc(mixed $doc): bool
    {
        return is_object($doc) && in_array((string) ($doc->cat ?? ''), self::DOC_CATS, true);
    }

    /**
     * De klant ziet van zo'n lijst alleen het openbare deel en zet daar iets
     * bij (vooraan of achteraan). Wat hij bijzette komt in de volledige
     * lijst; wat hij niet zag, blijft staan en niets gaat eruit.
     */
    private function added(mixed $old, mixed $new, string $rule, int $max = 20): array
    {
        $old = is_array($old) ? array_values($old) : [];
        $new = is_array($new) ? array_values($new) : [];
        $seen = array_values(array_filter($old, [self::class, $rule]));
        $extra = count($new) - count($seen);
        if ($extra <= 0 || $extra > $max) {
            return $old;
        }

        $json = fn (array $list) => array_map([Json::class, 'encode'], $list);
        $have = $json($old);
        // Een scherm dat nog niet is bijgewerkt, stuurt een eerdere toevoeging nog eens mee: die telt één keer.
        $fresh = fn (array $items) => array_values(array_filter($items, fn ($item) => ! in_array(Json::encode($item), $have, true)));

        if ($json(array_slice($new, $extra)) === $json($seen)) {
            return [...$fresh(array_slice($new, 0, $extra)), ...$old]; // vooraan bijgezet
        }
        if ($json(array_slice($new, 0, count($seen))) === $json($seen)) {
            return [...$old, ...$fresh(array_slice($new, count($seen)))]; // achteraan bijgezet
        }

        return $old;
    }

    private function employee(mixed $e): object
    {
        $out = new \stdClass;
        foreach (self::EMP_FIELDS as $field) {
            if (is_object($e) && property_exists($e, $field)) {
                $out->{$field} = $e->{$field};
            }
        }
        $out->certs = [];
        $out->afwezig = [];

        return $out;
    }

    /** Wat er bij ondertekenen vanzelf ontstaat, mag; een betaalde factuur of een ander bedrag niet. */
    private function harmlessNew(string $collection, object $item): bool
    {
        return match ($collection) {
            'invoices' => ($item->status ?? '') === 'concept' && empty($item->betaald),
            default => true,
        };
    }

    /**
     * De klant verandert aan zijn project alleen: akkoord op een optie
     * (meerwerk), ondertekenen van een verzonden offerte, een bezoek aan de
     * offerte en aangeleverde documenten. Prijzen, regels en planning
     * blijven zoals ze op de server staan.
     */
    private function projectWrite(object $old, object $new): object
    {
        $out = clone $old;

        $out->activity = $this->added($old->activity ?? [], $new->activity ?? [], 'publicActivity');
        $out->docs = $this->added($old->docs ?? [], $new->docs ?? [], 'publicDoc');

        // Akkoord op meerwerk: alleen van optie naar akkoord.
        if (isset($old->begroting->options, $new->begroting->options) && is_array($old->begroting->options)) {
            $out->begroting = clone $old->begroting;
            $options = [];
            foreach ($old->begroting->options as $i => $option) {
                $option = clone $option;
                $sent = $new->begroting->options[$i] ?? null;
                if (($option->s ?? '') === 'optie' && is_object($sent) && ($sent->s ?? '') === 'akkoord') {
                    $option->s = 'akkoord';
                }
                $options[] = $option;
            }
            $out->begroting->options = $options;
        }

        $offer = $old->offerte ?? null;
        $sentOffer = $new->offerte ?? null;
        if (is_object($offer) && is_object($sentOffer)) {
            $out->offerte = clone $offer;
            $out->offerte->views = Json::appendOnly($offer->views ?? [], $sentOffer->views ?? [], 3);

            // Ondertekenen: alleen een verzonden offerte, en het bedrag blijft wat Renovion verstuurde.
            if (($offer->status ?? '') === 'verzonden' && ($sentOffer->status ?? '') === 'geaccepteerd') {
                foreach (['status', 'accepted', 'signedBy', 'signed', 'ip'] as $field) {
                    if (property_exists($sentOffer, $field)) {
                        $out->offerte->{$field} = $sentOffer->{$field};
                    }
                }
                if (isset($out->begroting) && is_object($out->begroting)) {
                    $out->begroting = clone $out->begroting;
                    $out->begroting->status = 'Definitief';
                }
                $out->phase = max((int) ($old->phase ?? 0), min(5, (int) ($new->phase ?? 0)));
                foreach (['start', 'eind'] as $field) {
                    if (empty($old->{$field}) && ! empty($new->{$field})) {
                        $out->{$field} = $new->{$field};
                    }
                }
            }
        }

        return $out;
    }

    private function filterOrder(array $scope, string $collection, string $value): ?string
    {
        $keys = (array) (Json::decode($value) ?? []);

        $visible = match ($collection) {
            'projects' => $scope['pids'],
            'clients' => array_filter([$scope['cid']]),
            'extraUsers' => array_filter([$scope['person']]),
            'emps' => $keys,
            default => in_array($collection, self::BY_PROJECT, true) ? null : [],
        };

        if ($visible === null) {
            // Lijst per project: de server weet pas welke sleutels zichtbaar zijn als hij ze leest.
            $own = OsRow::query()->where('collection', $collection)->whereNotNull('value')->where('path', 'not like', '%/#order')
                ->get(['path', 'value'])->filter(fn ($r) => in_array((string) Json::get(Json::decode($r->value), 'pid'), $scope['pids'], true))
                ->map(fn ($r) => Layout::keyOf($r->path))->all();
            $visible = $own;
        }

        return Json::encode(array_values(array_filter($keys, fn ($k) => in_array($k, $visible, true))));
    }
}
