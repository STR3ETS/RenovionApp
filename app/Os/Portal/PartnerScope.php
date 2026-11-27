<?php

namespace App\Os\Portal;

use App\Models\OsRow;
use App\Models\User;
use App\Os\Layout;

/**
 * Wat een onderaannemer ziet en mag in het partnerportaal.
 *
 * Hij ziet zijn eigen bedrijfsdossier, de prijsaanvragen waarvoor hij is
 * uitgenodigd of die hij gegund kreeg (zonder budget en zonder de prijzen van
 * anderen), zijn eigen werkbonnen en klussen, en van de projecten alleen
 * naam en adres. Hij levert zijn documenten aan, dient een prijs in en
 * werkt zijn werkbonnen bij; goedkeuren en keuren doet Renovion.
 *
 * In het portaal geeft hij ook zijn beschikbaarheid door (partnerBesch), stelt
 * hij vragen (partnerVragen) en dient hij facturen in (partnerFact). Daarvan
 * ziet hij alleen wat van zijn eigen bedrijf is; koppelen aan een project,
 * antwoorden en goedkeuren doet Renovion.
 */
final class PartnerScope
{
    private const WHOLE_READ = ['v', 'seq', 'prefs', 'autoInit'];

    /** Wat Renovion over een partner beslist; dat past hij zelf niet aan. */
    private const SUB_LOCKED = ['keurmerk', 'blocked', 'blockReden', 'rating', 'checks', 'idCheck', 'raam', 'opdrachten', 'empId', 'tarief'];

    /** Velden van een werkbon die de partner zelf invult. */
    /** Wat de partner zelf in het portaal aanlevert: één regel per bericht, altijd met zijn eigen bedrijf in `sub`. */
    private const OWN = ['partnerBesch', 'partnerVragen', 'partnerFact'];

    private const WB_FIELDS = ['uren', 'checklist', 'fotos', 'materialen', 'opm', 'obstakels', 'getekend', 'notitie', 'notities', 'opmerking', 'eind', 'log', 'handtekening', 'sig', 'start', 'pauze'];

    private const WB_OWN_STATUS = ['uitgegeven', 'uitvoering', 'controle'];

    private const PROJECT_FIELDS = ['id', 'naam', 'adres', 'plaats', 'type', 'phase', 'leider', 'progress', 'planned', 'kleur', 'start', 'eind'];

    private const EMP_FIELDS = ['id', 'naam', 'rol', 'type', 'tel', 'mail', 'sysrol', 'status'];

    /** @return array{person: ?string, sid: ?string, rfqs: list<string>, pids: list<string>, blocks: list<string>} */
    public static function for(User $user): array
    {
        $person = $user->person_id;
        $sid = null;
        $mail = strtolower($user->email);

        foreach (OsRow::query()->where('collection', 'subs')->whereNotNull('value')->where('path', 'not like', '%/#order')->pluck('value') as $json) {
            $s = Json::decode($json);
            if (is_object($s) && (($person && (($s->id ?? null) === $person || ($s->empId ?? null) === $person)) || strtolower((string) ($s->mail ?? '')) === $mail)) {
                $sid = (string) $s->id;
                break;
            }
        }

        $rfqs = [];
        $pids = [];
        if ($sid) {
            foreach (OsRow::query()->where('collection', 'rfqs')->whereNotNull('value')->where('path', 'not like', '%/#order')->pluck('value') as $json) {
                $r = Json::decode($json);
                if (is_object($r) && (in_array($sid, (array) ($r->invited ?? []), true) || ($r->awardedTo ?? null) === $sid)) {
                    $rfqs[] = (string) $r->id;
                    $pids[] = (string) ($r->pid ?? '');
                }
            }
        }

        $blocks = [];
        if ($person) {
            foreach (OsRow::query()->whereIn('collection', ['werkbonnen', 'blocks'])->whereNotNull('value')->where('path', 'not like', '%/#order')->get(['collection', 'path', 'value']) as $row) {
                $x = Json::decode($row->value);
                if (is_object($x) && ($x->emp ?? null) === $person) {
                    $pids[] = (string) ($x->pid ?? '');
                    if ($row->collection === 'blocks') {
                        $blocks[] = (string) $x->id;
                    }
                }
            }
        }

        return ['person' => $person, 'sid' => $sid, 'rfqs' => $rfqs, 'pids' => array_values(array_unique(array_filter($pids))), 'blocks' => $blocks];
    }

    public function read(array $scope, string $path, string $collection, string $value): ?string
    {
        $key = Layout::keyOf($path);

        if ($key === null) {
            return in_array($collection, self::WHOLE_READ, true) || preg_match('/^r\d+[a-z]*$/', $collection) ? $value : null;
        }

        if ($key === '#order') {
            $keys = (array) (Json::decode($value) ?? []);
            $visible = match ($collection) {
                'subs' => array_filter([$scope['sid']]),
                'rfqs' => $scope['rfqs'],
                'projects' => $scope['pids'],
                'blocks' => $scope['blocks'],
                'emps' => $keys,
                'werkbonnen' => null,
                'partnerBesch', 'partnerVragen', 'partnerFact' => $this->ownKeys($collection, $scope['sid']),
                default => [],
            };
            if ($visible === null) {
                return Json::encode(array_values(array_filter($keys, fn ($k) => $this->ownWorkOrder($scope, $k))));
            }

            return Json::encode(array_values(array_filter($keys, fn ($k) => in_array($k, $visible, true))));
        }

        $item = Json::decode($value);

        return match ($collection) {
            'subs' => $key === $scope['sid'] ? $value : null,
            'rfqs' => in_array($key, $scope['rfqs'], true) ? Json::encode($this->rfq($item, $scope['sid'])) : null,
            'werkbonnen' => is_object($item) && ($item->emp ?? null) === $scope['person'] ? $value : null,
            'blocks' => in_array($key, $scope['blocks'], true) ? $value : null,
            'klus' => in_array($key, $scope['blocks'], true) ? $value : null,
            'projects' => in_array($key, $scope['pids'], true) ? Json::encode($this->project($item)) : null,
            'emps' => $key === $scope['person'] ? $value : Json::encode($this->pick($item, self::EMP_FIELDS, ['certs' => [], 'afwezig' => []])),
            'partnerBesch', 'partnerVragen', 'partnerFact' => $scope['sid'] && is_object($item) && ($item->sub ?? null) === $scope['sid'] ? $value : null,
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
            return false;
        }

        $item = Json::decode($new);
        $prev = Json::decode($old);

        switch ($collection) {
            case 'subs':
                if ($key !== $scope['sid'] || ! is_object($prev) || ! is_object($item)) {
                    return false;
                }
                // Tijdens het aanmelden (keurmerk "nieuw") geeft de partner zelf zijn tarief op en rondt hij de
                // aanmelding af: dan gaat het dossier naar "in beoordeling". Daarna beslist Renovion.
                $onboarding = ($prev->keurmerk ?? null) === 'nieuw';
                $done = $onboarding && ($item->keurmerk ?? null) === 'beoordeling' && Json::get($item, 'inv.done') === true;
                foreach (self::SUB_LOCKED as $field) {
                    if ($onboarding && $field === 'tarief' && is_numeric($item->tarief ?? null)) {
                        continue;
                    }
                    if (property_exists($prev, $field)) {
                        $item->{$field} = $prev->{$field};
                    } else {
                        unset($item->{$field});
                    }
                }
                if ($done) {
                    $item->keurmerk = 'beoordeling';
                }

                // Documenten levert de partner zelf aan (net als in het prototype); het keurmerk geeft Renovion.
                return Json::encode($item);

            case 'rfqs':
                if (! in_array($key, $scope['rfqs'], true) || ! is_object($prev)) {
                    return false;
                }
                $out = clone $prev;
                $bids = array_values(array_filter((array) ($prev->bids ?? []), fn ($b) => ($b->sub ?? null) !== $scope['sid']));
                $mine = array_values(array_filter((array) ($item->bids ?? []), fn ($b) => is_object($b) && ($b->sub ?? null) === $scope['sid']));
                $out->bids = [...$bids, ...array_slice($mine, 0, 1)];

                return Json::encode($out);

            case 'werkbonnen':
                if (! is_object($prev) || ($prev->emp ?? null) !== $scope['person'] || ! is_object($item)) {
                    return false;
                }
                $out = Json::onlyFields($prev, $item, self::WB_FIELDS);
                if (in_array($item->status ?? '', self::WB_OWN_STATUS, true) && in_array($prev->status ?? '', self::WB_OWN_STATUS, true)) {
                    $out->status = $item->status;
                }

                return Json::encode($out);

            case 'klus':
                return in_array($key, $scope['blocks'], true);

            case 'partnerBesch':
            case 'partnerVragen':
            case 'partnerFact':
                return $this->own($collection, $scope, $key, $prev, $item);

            case 'notifs':
            case 'outbox':
            case 'ai.audit':
                return $prev === null;

            default:
                return false;
        }
    }

    /**
     * Beschikbaarheid, vragen en facturen van de partner zelf. Hij zet er nieuwe bij voor zijn eigen bedrijf en
     * trekt een beschikbaarheid of factuur in zolang Renovion er nog niets mee deed; de status verder is van Renovion.
     */
    private function own(string $collection, array $scope, string $key, mixed $prev, mixed $item): bool|string
    {
        $sid = $scope['sid'];
        if (! $sid || ! is_object($item) || ($item->sub ?? null) !== $sid || (string) ($item->id ?? '') !== $key) {
            return false;
        }
        $text = fn (string $field, int $max) => mb_substr(trim((string) (is_scalar($item->{$field} ?? null) ? $item->{$field} : '')), 0, $max);
        $date = fn (string $field) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) (is_scalar($item->{$field} ?? null) ? $item->{$field} : '')) === 1 ? (string) $item->{$field} : null;
        $today = now()->toDateString();

        if ($prev !== null) {
            // Bestaand: alleen wijzigen of intrekken wat nog open staat (een vraag blijft staan zoals hij is gesteld).
            $open = ['partnerBesch' => 'open', 'partnerFact' => 'ingediend'][$collection] ?? null;
            if (! is_object($prev) || ($prev->sub ?? null) !== $sid || $open === null || ($prev->status ?? null) !== $open) {
                return false;
            }
            $out = clone $prev;
            if (($item->status ?? null) === 'ingetrokken') {
                $out->status = 'ingetrokken';
            } elseif ($collection === 'partnerBesch') {
                $van = $date('van');
                $tot = $date('tot');
                if ($van && $tot && $tot >= $van) {
                    $out->van = $van;
                    $out->tot = $tot;
                    $out->note = $text('note', 500);
                }
            }

            return Json::encode($out);
        }

        if ($collection === 'partnerBesch') {
            $van = $date('van');
            $tot = $date('tot');

            return $van && $tot && $tot >= $van
                ? Json::encode(['id' => $key, 'sub' => $sid, 'van' => $van, 'tot' => $tot, 'note' => $text('note', 500), 'd' => $today, 'status' => 'open'])
                : false;
        }

        if ($collection === 'partnerVragen') {
            $txt = $text('txt', 4000);

            return $txt === '' ? false : Json::encode(array_filter([
                'id' => $key, 'sub' => $sid, 'van' => 'partner', 'naam' => $text('naam', 120), 'txt' => $txt,
                'd' => $today, 'tijd' => $text('tijd', 5), 'over' => $text('over', 160),
            ], fn ($v) => $v !== ''));
        }

        $url = $text('url', 200);
        if (! is_numeric($item->bedrag ?? null) || (float) $item->bedrag <= 0 || $text('nr', 60) === '' || ($url !== '' && ! str_starts_with($url, '/os/bestanden/'))) {
            return false;
        }
        $btw = (int) (is_numeric($item->btw ?? null) ? $item->btw : 21);

        return Json::encode(array_filter([
            'id' => $key, 'sub' => $sid, 'nr' => $text('nr', 60), 'datum' => $date('datum') ?? $today, 'bedrag' => round((float) $item->bedrag, 2),
            'btw' => in_array($btw, [0, 9, 21], true) ? $btw : 21,
            'oms' => $text('oms', 300), 'ref' => $text('ref', 160), 'pid' => $text('pid', 40), 'bestand' => $text('bestand', 200), 'url' => $url,
            'd' => $today, 'status' => 'ingediend',
        ], fn ($v) => $v !== ''));
    }

    /** @return list<string> de sleutels in een eigen collectie die van dit partnerbedrijf zijn */
    private function ownKeys(string $collection, ?string $sid): array
    {
        if (! $sid || ! in_array($collection, self::OWN, true)) {
            return [];
        }
        $keys = [];
        foreach (OsRow::query()->where('collection', $collection)->whereNotNull('value')->where('path', 'not like', '%/#order')->get(['path', 'value']) as $row) {
            $x = Json::decode($row->value);
            if (is_object($x) && ($x->sub ?? null) === $sid) {
                $keys[] = (string) Layout::keyOf($row->path);
            }
        }

        return $keys;
    }

    private function ownWorkOrder(array $scope, string $key): bool
    {
        $x = Json::decode(OsRow::query()->where('path', 'werkbonnen/'.$key)->value('value'));

        return is_object($x) && ($x->emp ?? null) === $scope['person'];
    }

    private function rfq(object $r, ?string $sid): object
    {
        $r->bids = array_values(array_filter((array) ($r->bids ?? []), fn ($b) => is_object($b) && ($b->sub ?? null) === $sid));
        $r->invited = array_values(array_filter((array) ($r->invited ?? []), fn ($s) => $s === $sid));
        unset($r->budget, $r->marge, $r->verkoop, $r->sale);

        return $r;
    }

    private function project(object $p): object
    {
        return $this->pick($p, self::PROJECT_FIELDS, [
            'clientId' => '', 'begroting' => null, 'offerte' => null, 'docs' => [], 'activity' => [], 'intake' => new \stdClass,
            'termijnen' => [], 'uren' => [], 'regieMat' => [], 'mw' => [], 'kosten' => 0, 'waarde' => 0, 'bouw' => new \stdClass,
        ]);
    }

    private function pick(mixed $item, array $fields, array $defaults = []): object
    {
        $out = (object) $defaults;
        foreach ($fields as $field) {
            if (is_object($item) && property_exists($item, $field)) {
                $out->{$field} = $item->{$field};
            }
        }

        return $out;
    }
}
