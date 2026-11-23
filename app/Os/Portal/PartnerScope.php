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
 */
final class PartnerScope
{
    private const WHOLE_READ = ['v', 'seq', 'prefs', 'autoInit'];

    /** Wat Renovion over een partner beslist; dat past hij zelf niet aan. */
    private const SUB_LOCKED = ['keurmerk', 'blocked', 'blockReden', 'rating', 'checks', 'idCheck', 'raam', 'opdrachten', 'empId', 'tarief'];

    /** Velden van een werkbon die de partner zelf invult. */
    private const WB_FIELDS = ['uren', 'checklist', 'fotos', 'materialen', 'notitie', 'notities', 'opmerking', 'eind', 'log', 'handtekening', 'sig', 'start', 'pauze'];

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
                foreach (self::SUB_LOCKED as $field) {
                    if (property_exists($prev, $field)) {
                        $item->{$field} = $prev->{$field};
                    } else {
                        unset($item->{$field});
                    }
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

            case 'notifs':
            case 'outbox':
            case 'ai.audit':
                return $prev === null;

            default:
                return false;
        }
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
