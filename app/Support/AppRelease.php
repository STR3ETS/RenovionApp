<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Verplichte update van de iOS/Android-app. De afspraak staat in mobile/release.json: "build" is het
 * buildnummer van de huidige app (Android versionCode, iOS CURRENT_PROJECT_VERSION), "minimum_build"
 * het laagste buildnummer dat nog mag werken. 0 = geen verplichte update (standaard).
 *
 * De app stuurt zijn buildnummer mee in de header X-App-Build; voor serverpagina's in de app onthoudt
 * de sessie het (app_build, via de eenmalige webcode). Een app zonder buildnummer is ouder dan deze
 * regeling en telt als build 0.
 */
class AppRelease
{
    public const HEADER = 'X-App-Build';

    /** @var array<string, mixed>|null */
    private static ?array $release = null;

    /** @return array<string, mixed> */
    public static function data(): array
    {
        if (self::$release === null) {
            $path = base_path('mobile/release.json');
            self::$release = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        }

        return self::$release;
    }

    public static function minimumBuild(): int
    {
        return (int) (self::data()['minimum_build'] ?? 0);
    }

    public static function currentBuild(): int
    {
        return (int) (self::data()['build'] ?? 1);
    }

    /** Het buildnummer van de app die dit verzoek doet; null als het niet de app is. */
    public static function buildOf(Request $request): ?int
    {
        if ($request->hasHeader(self::HEADER)) {
            return (int) $request->header(self::HEADER);
        }

        if ($request->hasSession() && $request->session()->has('app_build')) {
            return (int) $request->session()->get('app_build');
        }

        return NativeApp::active() || $request->is('api/app/*') ? 0 : null;
    }

    public static function mustUpdate(?int $build): bool
    {
        return $build !== null && self::minimumBuild() > 0 && $build < self::minimumBuild();
    }

    /** De link naar de nieuwe versie voor dit toestel, of null als die er (nog) niet is. */
    public static function link(Request $request): ?string
    {
        $platform = preg_match('/iPhone|iPad|iOS/i', (string) $request->userAgent()) ? 'ios' : 'android';

        return self::data()['links'][$platform] ?? null;
    }

    /** @return array<string, mixed> */
    public static function payload(Request $request): array
    {
        return [
            'message' => 'Er is een nieuwe versie van Renovion OS. Download de nieuwste versie om verder te werken.',
            'update' => [
                'required' => true,
                'minimum_build' => self::minimumBuild(),
                'url' => self::link($request),
            ],
        ];
    }

    /** Alleen voor tests: opnieuw inlezen. */
    public static function flush(?array $release = null): void
    {
        self::$release = $release;
    }
}
