<?php

namespace App\Os\Ai;

use App\Models\User;
use App\Os\Layout;
use App\Os\StateStore;

/**
 * De staat zoals een gebruiker hem mag zien, als gewone PHP-lijsten, voor
 * Nova en de agents. Een klant of partner krijgt hier dus ook alleen zijn
 * eigen deel (de regels komen via StateStore::snapshot).
 */
final class StateReader
{
    /** @var array<string, list<array<string, mixed>>> */
    public array $lists = [];

    /** @var array<string, array<string, mixed>> */
    public array $maps = [];

    /** @var array<string, mixed> */
    public array $whole = [];

    public function __construct(public readonly User $user)
    {
        $snapshot = app(StateStore::class)->snapshot($user);
        $items = [];
        $orders = [];

        foreach ($snapshot['rows'] as [$path, $json]) {
            if ($json === null || str_starts_with($path, '~')) {
                continue;
            }
            $value = json_decode($json, true);
            $key = Layout::keyOf($path);
            $collection = Layout::collectionOf($path);

            if ($key === null) {
                $this->whole[$collection] = $value;
            } elseif (in_array($collection, Layout::maps(), true)) {
                $this->maps[$collection][$key] = $value;
            } elseif ($key === '#order') {
                $orders[$collection] = (array) $value;
            } else {
                $items[$collection][$key] = $value;
            }
        }

        foreach ($items as $collection => $byKey) {
            $list = [];
            foreach ($orders[$collection] ?? [] as $key) {
                if (isset($byKey[$key])) {
                    $list[] = $byKey[$key];
                    unset($byKey[$key]);
                }
            }
            $this->lists[$collection] = [...$list, ...array_values($byKey)];
        }
    }

    /** @return list<array<string, mixed>> */
    public function list(string $collection): array
    {
        return $this->lists[$collection] ?? [];
    }

    /** @return array<string, mixed>|null */
    public function find(string $collection, string $field, mixed $value): ?array
    {
        foreach ($this->list($collection) as $item) {
            if (($item[$field] ?? null) === $value) {
                return $item;
            }
        }

        return null;
    }

    public function project(?string $id): ?array
    {
        return $id ? $this->find('projects', 'id', strtoupper($id)) : null;
    }

    public function client(?string $id): ?array
    {
        return $id ? $this->find('clients', 'id', $id) : null;
    }

    public function person(?string $id): ?array
    {
        return $id ? ($this->find('emps', 'id', $id) ?? $this->find('extraUsers', 'id', $id)) : null;
    }

    public function personName(?string $id): string
    {
        return (string) ($this->person($id)['naam'] ?? ($id ?: 'niemand'));
    }

    public function me(): ?array
    {
        return $this->person($this->user->person_id);
    }

    public function myName(): string
    {
        return (string) ($this->me()['naam'] ?? $this->user->name);
    }

    /** De fasen van een dossier, zoals in het prototype (PHASES). */
    public const PHASES = ['Aanvraag', 'Intake', 'Begroting', 'Offerte', 'Akkoord', 'Voorbereiding', 'Uitvoering', 'Oplevering', 'Nazorg'];

    public static function phase(mixed $index): string
    {
        return self::PHASES[(int) $index] ?? 'Onbekend';
    }

    /**
     * Totalen van een begroting, dezelfde rekenregel als calcB in de app:
     * regel = q * (n * tarief + m + e + o) + v, de staart cumulatief.
     *
     * @return array{direct: float, excl: float, regels: int, zonder_prijs: list<array<string, mixed>>}
     */
    public static function budgetTotals(?array $b): array
    {
        $out = ['direct' => 0.0, 'excl' => 0.0, 'regels' => 0, 'zonder_prijs' => []];
        if (! $b) {
            return $out;
        }

        $rate = (float) ($b['rate'] ?? 55);
        foreach ($b['chapters'] ?? [] as $chapter) {
            foreach ($chapter['pars'] ?? [] as $par) {
                foreach ($par['lines'] ?? [] as $line) {
                    if (($line['t'] ?? 'line') === 'text') {
                        continue;
                    }
                    $q = (float) ($line['q'] ?? 0);
                    $r = ($line['r'] ?? null) === null || $line['r'] === '' ? $rate : (float) $line['r'];
                    $sum = $q * ((float) ($line['n'] ?? 0) * $r + (float) ($line['m'] ?? 0) + (float) ($line['e'] ?? 0) + (float) ($line['o'] ?? 0)) + (float) ($line['v'] ?? 0);
                    $out['direct'] += $sum;
                    $out['regels']++;
                    $priced = (float) ($line['n'] ?? 0) || (float) ($line['m'] ?? 0) || (float) ($line['e'] ?? 0) || (float) ($line['o'] ?? 0) || (float) ($line['v'] ?? 0);
                    if ($q > 0 && ! $priced) {
                        $out['zonder_prijs'][] = ['regel_id' => $line['id'] ?? '', 'hoofdstuk' => trim(($chapter['code'] ?? '').' '.($chapter['name'] ?? '')), 'omschrijving' => $line['d'] ?? '', 'hoeveelheid' => $q, 'eenheid' => $line['u'] ?? ''];
                    }
                }
            }
        }

        $cumulative = 0.0;
        foreach ($b['staart'] ?? [] as $tail) {
            $cumulative += ($out['direct'] + $cumulative) * ((float) ($tail['pct'] ?? 0)) / 100;
        }
        $out['excl'] = round($out['direct'] + $cumulative, 2);
        $out['direct'] = round($out['direct'], 2);

        return $out;
    }

    /** De komende twee weken met dagnamen, zodat "vrijdag" of "volgende week dinsdag" goed wordt omgerekend. */
    public static function calendar(string $today): string
    {
        $names = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
        $out = [];
        for ($i = 0; $i < 15; $i++) {
            $t = strtotime($today.' +'.$i.' days');
            $out[] = $names[(int) date('w', $t)].' '.date('Y-m-d', $t).($i === 0 ? ' (vandaag)' : ($i === 1 ? ' (morgen)' : ''));
        }

        return implode(', ', $out);
    }

    public static function euro(float $value): string
    {
        return '€ '.number_format($value, 2, ',', '.');
    }
}
