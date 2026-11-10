<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * De toegangscodes van de offertetool, nagerekend op de server.
 *
 * Het team maakt codes in de tool zelf. De tool bewaart per persoon alleen een
 * hash: sha256(salt | telefoon | code). Hier staat dezelfde rekenregel, zodat
 * de server kan controleren zonder dat de code ooit ergens staat.
 */
final class OfferToolAccess
{
    /** De tool schrijft "van" en "tot" in lokale tijd, zonder tijdzone erbij. */
    public const TIMEZONE = 'Europe/Amsterdam';

    public const OPEN = 'open';

    public const CLOSED = 'dicht';

    public const EXPIRED = 'verlopen';

    // Zo gespeld in de tool en in de inlogpagina, dus zo moet hij terug.
    public const NOT_YET = 'nognniet';

    /** Alleen cijfers; 0031 en 31 vooraan worden een 0. */
    public static function phone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '0031')) {
            return '0'.substr($digits, 4);
        }

        if (str_starts_with($digits, '31') && strlen($digits) === 11) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    /** Hoofdletters, alleen 0-9 en A-Z. */
    public static function code(?string $code): string
    {
        return preg_replace('/[^0-9A-Z]/', '', strtoupper((string) $code)) ?? '';
    }

    /** Een deelcode van acht tekens werkt zonder telefoonnummer. */
    public static function hash(object $person, ?string $phone, ?string $code): string
    {
        $phonePart = empty($person->codeOnly) ? self::phone($phone) : '';

        return hash('sha256', ($person->salt ?? '').'|'.$phonePart.'|'.self::code($code));
    }

    /**
     * @return list<object>
     */
    public static function persons(?object $model): array
    {
        $persons = $model?->access?->persons ?? [];

        return array_values(array_filter(
            is_array($persons) ? $persons : [],
            fn ($person) => is_object($person) && ! empty($person->id) && ! empty($person->codeHash),
        ));
    }

    /** Wie hoort bij dit nummer en deze code? Vergelijkt in constante tijd. */
    public static function find(?object $model, ?string $phone, ?string $code): ?object
    {
        $codeOnly = strlen(self::code($code)) === 8;
        $found = null;

        // Bewust geen vroege return: elke kandidaat kost evenveel tijd.
        foreach (self::persons($model) as $person) {
            if (! empty($person->codeOnly) !== $codeOnly) {
                continue;
            }

            if (hash_equals((string) $person->codeHash, self::hash($person, $phone, $code))) {
                $found ??= $person;
            }
        }

        return $found;
    }

    /**
     * De persoon van een lopende sessie, zolang zijn code dezelfde is. Een
     * vernieuwde code maakt de oude sessie dus direct ongeldig.
     */
    public static function person(?object $model, ?string $id, ?string $codeHash): ?object
    {
        foreach (self::persons($model) as $person) {
            if ((string) $person->id === (string) $id && hash_equals((string) $person->codeHash, (string) $codeHash)) {
                return $person;
            }
        }

        return null;
    }

    /**
     * Open, dicht, verlopen of nog niet open. Geldig is alleen: de offerte staat
     * open, de persoon staat open, en het is nu tussen "van" en "tot".
     */
    public static function state(?object $model, object $person, ?CarbonInterface $now = null): string
    {
        if (empty($model?->access?->open ?? true) || empty($person->open)) {
            return self::CLOSED;
        }

        $moment = ($now ?? now())->copy()->setTimezone(self::TIMEZONE)->format('Y-m-d\TH:i');

        if (! empty($person->from) && $moment < self::from((string) $person->from)) {
            return self::NOT_YET;
        }

        if (! empty($person->until) && $moment > self::until((string) $person->until)) {
            return self::EXPIRED;
        }

        return self::OPEN;
    }

    /** Een datum zonder tijd begint om middernacht. */
    public static function from(string $value): string
    {
        return strlen($value) === 10 ? $value.'T00:00' : $value;
    }

    /** Een datum zonder tijd loopt tot het eind van die dag. */
    public static function until(string $value): string
    {
        return strlen($value) === 10 ? $value.'T23:59' : $value;
    }
}
