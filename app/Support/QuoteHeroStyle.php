<?php

namespace App\Support;

/**
 * Achtergrond van de offertekop (hero-blok): effen kleur, foto, of foto met
 * een kleur-overlay. Rekent de CSS-variabelen uit waarmee de kop zijn tekst
 * leesbaar houdt op een lichte of donkere achtergrond. De browser-kant in de
 * builder (heroResolve in builder.blade.php) volgt dezelfde regels.
 */
final class QuoteHeroStyle
{
    public const DEFAULT_BG = '#070724';

    public const DEFAULT_OVERLAY = 55;

    /**
     * Vaste kleurkeuzes in de builder.
     *
     * @return array<string, string> label => hex
     */
    public static function presets(): array
    {
        return [
            'Navy' => '#070724',
            'Oranje' => '#f85b0b',
            'Antraciet' => '#1f2937',
            'Zwart' => '#111111',
            'Diepgroen' => '#14342b',
            'Zand' => '#f3eee6',
            'Wit' => '#ffffff',
        ];
    }

    /**
     * @param  array<string, mixed>  $data  blokdata van de hero
     * @return array{bg: string, image: string|null, overlay: int, text: string, theme: string, style: string}
     */
    public static function resolve(array $data): array
    {
        $bg = self::color($data['bg'] ?? null) ?? self::DEFAULT_BG;
        $image = self::imageUrl($data['image'] ?? null);
        $overlay = max(0, min(95, (int) ($data['overlay'] ?? self::DEFAULT_OVERLAY)));
        $text = in_array($data['text'] ?? null, ['light', 'dark'], true) ? $data['text'] : 'auto';

        $theme = $text !== 'auto'
            ? $text
            : (($image !== null && $overlay < 35) ? 'light' : (self::luminance($bg) > 0.6 ? 'dark' : 'light'));

        $vars = $theme === 'dark'
            ? ['fg' => '#070724', 'soft' => '#1f204f', 'muted' => '#414382', 'accent' => '#e04c06', 'line' => 'rgba(7,7,36,.12)', 'chip' => 'rgba(7,7,36,.06)']
            : ['fg' => '#ffffff', 'soft' => '#d9d9eb', 'muted' => '#8b8cc0', 'accent' => '#fc7737', 'line' => 'rgba(255,255,255,.12)', 'chip' => 'rgba(255,255,255,.1)'];

        // Oranje accent op een oranje (of vergelijkbare) achtergrond valt weg: dan de tekstkleur.
        if ($image === null && self::contrast($vars['accent'], $bg) < 2.0) {
            $vars['accent'] = $vars['fg'];
        }

        $style = sprintf(
            '--hero-bg: %s; --hero-fg: %s; --hero-fg-soft: %s; --hero-fg-muted: %s; --hero-accent: %s; --hero-line: %s; --hero-chip: %s; background-color: %s;',
            $bg, $vars['fg'], $vars['soft'], $vars['muted'], $vars['accent'], $vars['line'], $vars['chip'], $bg,
        );

        if ($image !== null) {
            [$r, $g, $b] = self::rgb($bg);
            $alpha = $overlay / 100;
            $style .= sprintf(
                ' background-image: linear-gradient(rgba(%d,%d,%d,%.2F), rgba(%d,%d,%d,%.2F)), url("%s"); background-size: cover; background-position: center;',
                $r, $g, $b, $alpha, $r, $g, $b, $alpha, $image,
            );
        }

        return compact('bg', 'image', 'overlay', 'text', 'theme', 'style');
    }

    /**
     * Alleen een volledige hex-kleur (#rrggbb); al het andere wordt genegeerd.
     */
    public static function color(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if (preg_match('/^#[0-9a-f]{6}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m) === 1) {
            return '#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3];
        }

        return null;
    }

    /**
     * Een absolute http(s)-URL of een pad op deze site, zonder tekens die uit
     * een CSS url("...") kunnen breken.
     */
    public static function imageUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return preg_match('~^(https?://[^\s"\'()<>\\\\]+|/[^\s"\'()<>\\\\]*)$~i', $value) === 1 ? $value : null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    /**
     * Relatieve luminantie (WCAG): 0 = zwart, 1 = wit.
     */
    private static function luminance(string $hex): float
    {
        $channels = array_map(function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}
