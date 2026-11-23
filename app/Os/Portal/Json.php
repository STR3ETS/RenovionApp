<?php

namespace App\Os\Portal;

/**
 * Hulpjes voor de portaalregels. Alles werkt met objecten (stdClass), zodat
 * een leeg object {} ook na een wijziging een object blijft en niet een
 * lege lijst [] wordt.
 */
final class Json
{
    public static function decode(?string $json): mixed
    {
        return $json === null ? null : json_decode($json, false);
    }

    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function get(mixed $node, string $dotted): mixed
    {
        foreach (explode('.', $dotted) as $part) {
            if (is_object($node) && property_exists($node, $part)) {
                $node = $node->{$part};
            } elseif (is_array($node) && array_key_exists($part, $node)) {
                $node = $node[$part];
            } else {
                return null;
            }
        }

        return $node;
    }

    /** Een volgorde-regel: de bestaande volgorde blijft, nieuwe sleutels van de gebruiker komen erbij. */
    public static function mergeOrder(?string $old, ?string $new, callable $mayAdd): string
    {
        $current = array_values(array_filter((array) (self::decode($old) ?? []), 'is_string'));
        $sent = array_values(array_filter((array) (self::decode($new) ?? []), 'is_string'));

        $added = array_values(array_filter($sent, fn ($k) => ! in_array($k, $current, true) && $mayAdd($k)));
        if (! $added) {
            return self::encode($current);
        }

        // Stond het nieuwe element vooraan bij de gebruiker, dan ook vooraan in de lijst.
        $front = $sent && in_array($sent[0], $added, true);

        return self::encode($front ? [...$added, ...$current] : [...$current, ...$added]);
    }

    /** Tellers (seq) gaan alleen omhoog. */
    public static function maxMerge(?string $old, ?string $new): string
    {
        $o = self::decode($old) ?? new \stdClass;
        $n = self::decode($new) ?? new \stdClass;
        foreach (get_object_vars($n) as $key => $value) {
            if (is_numeric($value) && (! isset($o->{$key}) || $value > $o->{$key})) {
                $o->{$key} = $value;
            }
        }

        return self::encode($o);
    }

    /**
     * Neem van $new alleen de velden in $allowed over, de rest blijft zoals
     * in $old. Werkt één niveau diep.
     *
     * @param  list<string>  $allowed
     */
    public static function onlyFields(object $old, object $new, array $allowed): object
    {
        $out = clone $old;
        foreach ($allowed as $field) {
            if (property_exists($new, $field)) {
                $out->{$field} = $new->{$field};
            }
        }

        return $out;
    }

    /** Lijst waar de gebruiker alleen iets bij mag zetten (vooraan of achteraan), niets uit mag halen. */
    public static function appendOnly(mixed $old, mixed $new, int $max = 20): array
    {
        $old = is_array($old) ? $old : [];
        $new = is_array($new) ? $new : [];
        $extra = count($new) - count($old);
        if ($extra <= 0 || $extra > $max) {
            return $old;
        }

        $oldJson = array_map([self::class, 'encode'], $old);
        if (array_map([self::class, 'encode'], array_slice($new, $extra)) === $oldJson) {
            return $new; // vooraan bijgezet
        }
        if (array_map([self::class, 'encode'], array_slice($new, 0, count($old))) === $oldJson) {
            return $new; // achteraan bijgezet
        }

        return $old;
    }
}
