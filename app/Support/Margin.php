<?php

namespace App\Support;

use App\Models\Setting;

/**
 * De marge op werk dat we inkopen en doorverkopen: wat er overblijft van de
 * verkoopprijs. Onder de baseline waarschuwen we, onder de bodem mag het alleen
 * met een reden en akkoord van de directie.
 */
final class Margin
{
    public const GREEN = 'groen';

    public const AMBER = 'oranje';

    public const RED = 'rood';

    /** Marge in procenten van de verkoop; null als er niets verkocht wordt. */
    public static function pct(float $sale, float $cost): ?float
    {
        return $sale > 0 ? round(($sale - $cost) / $sale * 100, 1) : null;
    }

    /** Waaronder we waarschuwen (standaard 17 procent). */
    public static function baseline(): float
    {
        return Setting::number('marge.baseline');
    }

    /** Waaronder gunnen alleen met reden en akkoord van de directie mag (standaard 12 procent). */
    public static function floor(): float
    {
        return Setting::number('marge.bodem');
    }

    public static function level(?float $pct): ?string
    {
        return match (true) {
            $pct === null => null,
            $pct < self::floor() => self::RED,
            $pct < self::baseline() => self::AMBER,
            default => self::GREEN,
        };
    }

    public static function badgeClasses(?float $pct): string
    {
        return match (self::level($pct)) {
            self::RED => 'bg-red-500/15 text-red-300',
            self::AMBER => 'bg-amber-500/15 text-amber-300',
            self::GREEN => 'bg-green-500/15 text-green-300',
            default => 'bg-ink/10 text-muted',
        };
    }

    public static function format(?float $pct): string
    {
        return $pct === null ? 'n.v.t.' : rtrim(rtrim(number_format($pct, 1, ',', '.'), '0'), ',').'%';
    }
}
