<?php

namespace App\Os;

use App\Models\OsRow;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * De gedeelde staat van Renovion OS: lezen, wijzigingen sinds een nummer, en
 * opslaan. Wat een portaalgebruiker (klant, onderaannemer) mag zien en
 * wijzigen, bepaalt Visibility; wie wat aan accounts mag doen, People.
 */
final class StateStore
{
    /** Een regel mag niet groter zijn dan dit; foto's horen als bestand, niet in de staat. */
    public const MAX_ROW_BYTES = 6_000_000;

    public function __construct(
        private readonly Visibility $visibility,
        private readonly People $people,
    ) {}

    public function rev(): int
    {
        return (int) DB::table('os_meta')->where('id', 1)->value('rev');
    }

    /** Leeg systeem? Dan eerst de fabrieksstaat inlezen (één keer, ook als twee schermen tegelijk starten). */
    public function ensureInstalled(): void
    {
        if (OsRow::query()->exists()) {
            return;
        }

        Cache::lock('os-install', 30)->block(30, function () {
            if (! OsRow::query()->exists()) {
                app(Factory::class)->install();
            }
        });
    }

    /**
     * Alles wat deze gebruiker ziet, als [pad, json]-paren.
     *
     * @return array{rev: int, rows: list<array{0: string, 1: string}>}
     */
    public function snapshot(User $user): array
    {
        $rev = $this->rev();

        return ['rev' => $rev, 'rows' => $this->visibleRows($user, OsRow::query()->whereNotNull('value'))];
    }

    /**
     * Wat er sinds $since veranderde (verwijderd = null).
     *
     * @return array{rev: int, rows: list<array{0: string, 1: ?string}>}
     */
    public function since(User $user, int $since): array
    {
        $rev = $this->rev();

        return ['rev' => $rev, 'rows' => $this->visibleRows($user, OsRow::query()->where('rev', '>', $since)->where('rev', '<=', $rev), true)];
    }

    /**
     * Sla wijzigingen op. Elk paar is [pad, json of null]. Wat de gebruiker
     * niet mag, wordt overgeslagen en teruggemeld.
     *
     * @param  list<array{0: string, 1: ?string}>  $changes
     * @return array{rev: int, rejected: list<string>, accounts: list<array<string, string>>}
     */
    public function apply(User $user, array $changes): array
    {
        $rejected = [];
        $accepted = [];

        $paths = [];
        foreach ($changes as $change) {
            $path = $this->ownPath($user, (string) ($change[0] ?? ''));
            $paths[] = $path;
        }
        $current = OsRow::query()->whereIn('path', array_filter($paths))->pluck('value', 'path');

        foreach ($changes as $i => $change) {
            $path = $paths[$i];
            $value = $change[1] ?? null;

            if ($path === null || ! Layout::isValidPath($path) || (is_string($value) && strlen($value) > self::MAX_ROW_BYTES)) {
                $rejected[] = (string) ($change[0] ?? '');

                continue;
            }

            if ($value !== null && ! is_string($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if ($value !== null && json_decode($value) === null && $value !== 'null') {
                $rejected[] = $path;

                continue;
            }

            $old = $current[$path] ?? null;
            // Eigen regels per gebruiker (gelezen berichten, voortgang Academy) mag iedereen schrijven.
            $allowed = str_starts_with($path, '~') ? true : $this->visibility->acceptWrite($user, $path, $old, $value);
            if ($allowed === false) {
                $rejected[] = $path;

                continue;
            }

            $value = $this->people->guard($user, $path, $old, is_string($allowed) ? $allowed : $value);
            $accepted[$path] = [$old, $value];
        }

        if (! $accepted) {
            return ['rev' => $this->rev(), 'rejected' => $rejected, 'accounts' => []];
        }

        $rev = DB::transaction(function () use ($user, $accepted) {
            DB::table('os_meta')->insertOrIgnore(['id' => 1, 'rev' => 0]);
            $rev = (int) DB::table('os_meta')->where('id', 1)->lockForUpdate()->value('rev') + 1;
            DB::table('os_meta')->where('id', 1)->update(['rev' => $rev]);

            $now = now();
            $rows = [];
            foreach ($accepted as $path => [, $value]) {
                $rows[] = [
                    'path' => $path,
                    'collection' => Layout::collectionOf($path),
                    'value' => $value,
                    'rev' => $rev,
                    'updated_by' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                OsRow::query()->upsert($chunk, ['path'], ['collection', 'value', 'rev', 'updated_by', 'updated_at']);
            }

            DB::table('os_changes')->insert([
                'rev' => $rev,
                'user_id' => $user->id,
                'paths' => json_encode(array_keys($accepted), JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ]);

            return $rev;
        });

        $accounts = $this->people->reconcile($user, $accepted);

        return ['rev' => $rev, 'rejected' => $rejected, 'accounts' => $accounts];
    }

    /**
     * Zoals deze paden nu op de server staan (voor wat de gebruiker ziet),
     * zodat de app een geweigerde wijziging kan terugzetten.
     *
     * @param  list<string>  $paths
     * @return list<array{0: string, 1: ?string}>
     */
    public function current(User $user, array $paths): array
    {
        if (! $paths) {
            return [];
        }

        $own = array_filter(array_map(fn ($p) => $this->ownPath($user, $p), $paths));
        $rows = $this->visibleRows($user, OsRow::query()->whereIn('path', $own), true);
        $found = array_column($rows, 0);
        foreach ($paths as $path) {
            if (! in_array($path, $found, true)) {
                $rows[] = [$path, null];
            }
        }

        return $rows;
    }

    /** "~me/academy" wordt "~7/academy"; andermans regels per gebruiker mag niemand schrijven. */
    private function ownPath(User $user, string $path): ?string
    {
        if (str_starts_with($path, '~me/')) {
            return '~'.$user->id.substr($path, 3);
        }

        return str_starts_with($path, '~') ? null : $path;
    }

    /**
     * @return list<array{0: string, 1: ?string}>
     */
    private function visibleRows(User $user, $query, bool $withDeleted = false): array
    {
        $out = [];
        $prefix = '~'.$user->id.'/';

        $query->select(['path', 'collection', 'value'])->orderBy('id')->chunk(500, function ($rows) use (&$out, $user, $prefix, $withDeleted) {
            foreach ($rows as $row) {
                if ($row->collection === '~user') {
                    if (str_starts_with($row->path, $prefix)) {
                        $out[] = ['~me/'.substr($row->path, strlen($prefix)), $row->value];
                    }

                    continue;
                }

                $value = $this->visibility->read($user, $row->path, $row->collection, $row->value);
                if ($value !== null || ($withDeleted && $row->value === null)) {
                    $out[] = [$row->path, $value];
                }
            }
        });

        return $out;
    }
}
