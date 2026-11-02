<?php

namespace App\Support;

/**
 * Aantal met het juiste enkelvoud of meervoud: "1 calculatie", "3 calculaties".
 */
class Tally
{
    public static function of(int $count, string $singular, string $plural): string
    {
        return $count.' '.self::word($count, $singular, $plural);
    }

    public static function word(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? $singular : $plural;
    }
}
