<?php

namespace App\Os;

/**
 * Hoe de staat in regels (os_rows) wordt opgeknipt. De indeling staat in
 * resources/js/os/layout.json, zodat de app en de server precies hetzelfde
 * doen.
 *
 * Paden:
 *  - "seq"                    een onderdeel als geheel
 *  - "projects/PD-2026-0001"  één element van een lijst (items) of object (maps)
 *  - "projects/#order"        de volgorde van een lijst
 *  - "~7/academy"             een onderdeel per gebruiker (perUser), hier van gebruiker 7
 */
final class Layout
{
    private static ?array $layout = null;

    public static function get(): array
    {
        return self::$layout ??= json_decode(
            file_get_contents(resource_path('js/os/layout.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** @return array<string, string> collectie => sleutelveld */
    public static function items(): array
    {
        return self::get()['items'];
    }

    /** @return list<string> */
    public static function maps(): array
    {
        return self::get()['maps'];
    }

    /** @return list<string> */
    public static function perUser(): array
    {
        return self::get()['perUser'];
    }

    /** De collectie waar een pad bij hoort, zoals die in os_rows.collection staat. */
    public static function collectionOf(string $path): string
    {
        if (str_starts_with($path, '~')) {
            return '~user';
        }

        $slash = strpos($path, '/');

        return $slash === false ? $path : substr($path, 0, $slash);
    }

    /** De sleutel binnen de collectie, of null voor een onderdeel als geheel. */
    public static function keyOf(string $path): ?string
    {
        $slash = strpos($path, '/');

        return $slash === false ? null : substr($path, $slash + 1);
    }

    /** Hoort dit pad bij een bekende vorm? Onbekende paden weigert de server. */
    public static function isValidPath(string $path): bool
    {
        if ($path === '' || strlen($path) > 191) {
            return false;
        }

        if (str_starts_with($path, '~')) {
            return (bool) preg_match('/^~(me|\d+)\/[A-Za-z0-9_.]+$/', $path)
                && in_array(substr($path, strpos($path, '/') + 1), self::perUser(), true);
        }

        $collection = self::collectionOf($path);
        $key = self::keyOf($path);

        if ($key === null) {
            return (bool) preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $collection)
                && ! isset(self::items()[$collection])
                && ! in_array($collection, self::maps(), true);
        }

        return $key !== '' && (isset(self::items()[$collection]) || in_array($collection, self::maps(), true));
    }

    /**
     * Knip een staat op in regels: pad => JSON. Dezelfde regels als rowsFromState
     * in resources/js/os/bridge.js; de server gebruikt dit alleen om de
     * fabrieksstaat in te lezen.
     *
     * @return array<string, string>
     */
    public static function toRows(object $state, int $userId): array
    {
        $rows = [];
        $items = self::items();
        $maps = self::maps();
        $perUser = self::perUser();

        foreach (get_object_vars($state) as $top => $value) {
            if (isset($items[$top])) {
                self::itemRows($top, $value, $rows);
            } elseif (in_array($top, $maps, true)) {
                foreach (get_object_vars((object) $value) as $key => $entry) {
                    $rows[$top.'/'.$key] = self::json($entry);
                }
            } elseif (in_array($top, $perUser, true)) {
                $rows['~'.$userId.'/'.$top] = self::json($value);
            } elseif (is_object($value) && self::nestedOf($top)) {
                $rest = new \stdClass;
                foreach (get_object_vars($value) as $sub => $subValue) {
                    $nested = $top.'.'.$sub;
                    if (isset($items[$nested])) {
                        self::itemRows($nested, $subValue, $rows);
                    } elseif (in_array($nested, $perUser, true)) {
                        $rows['~'.$userId.'/'.$nested] = self::json($subValue);
                    } else {
                        $rest->{$sub} = $subValue;
                    }
                }
                $rows[$top] = self::json($rest);
            } else {
                $rows[$top] = self::json($value);
            }
        }

        return $rows;
    }

    /** @return list<string> de onderdelen van $top die apart worden opgeslagen */
    public static function nestedOf(string $top): array
    {
        $out = [];
        foreach ([...array_keys(self::items()), ...self::perUser()] as $name) {
            if (str_starts_with($name, $top.'.')) {
                $out[] = substr($name, strlen($top) + 1);
            }
        }

        return $out;
    }

    /**
     * De lege vorm van de staat: elke lijst leeg, elk object leeg, de rest
     * zoals in de fabrieksstaat. Zo bestaat elk onderdeel altijd, ook voor
     * een portaalgebruiker die maar een deel ziet.
     */
    public static function skeleton(object $factory): object
    {
        $skeleton = json_decode(json_encode($factory));

        foreach (self::items() as $collection => $field) {
            self::setPath($skeleton, $collection, []);
        }
        foreach (self::maps() as $collection) {
            $skeleton->{$collection} = new \stdClass;
        }

        return $skeleton;
    }

    private static function setPath(object $target, string $dotted, mixed $value): void
    {
        $parts = explode('.', $dotted);
        $last = array_pop($parts);
        foreach ($parts as $part) {
            if (! isset($target->{$part}) || ! is_object($target->{$part})) {
                $target->{$part} = new \stdClass;
            }
            $target = $target->{$part};
        }
        $target->{$last} = $value;
    }

    private static function itemRows(string $collection, mixed $list, array &$rows): void
    {
        $field = self::items()[$collection];
        $order = [];

        foreach ((array) $list as $item) {
            $key = $field !== '_k' && isset($item->{$field}) && $item->{$field} !== '' ? (string) $item->{$field} : ($item->_k ?? null);
            if ($key === null || in_array($key, $order, true)) {
                $key = $item->_k = 'k'.bin2hex(random_bytes(5));
            }
            $order[] = $key;
            $rows[$collection.'/'.$key] = self::json($item);
        }

        $rows[$collection.'/#order'] = self::json($order);
    }

    private static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
