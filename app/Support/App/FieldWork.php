<?php

namespace App\Support\App;

use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Hulpjes voor de uitvoeringsschermen in de app (klussen, werkbonnen, kwaliteit, oplevering, nazorg).
 *
 * De acties van die schermen lopen via dezelfde controllers als de website. Die antwoorden de website
 * zoals altijd (terug met een melding) en de app met JSON: een melding bij succes, 422 met de reden bij
 * een weigering. Foto's krijgen een ondertekend adres, want een <img> in de app stuurt geen token mee.
 */
class FieldWork
{
    /** Gelukt: de website gaat terug (of naar $redirect) met de melding, de app krijgt de melding als JSON. */
    public static function done(Request $request, string $message, array $data = [], ?RedirectResponse $redirect = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, ...$data]);
        }

        return ($redirect ?? back())->with('success', $message);
    }

    /** Geweigerd: de website gaat terug met de foutmelding, de app krijgt 422 met dezelfde tekst. */
    public static function refuse(Request $request, string $message, ?RedirectResponse $redirect = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return ($redirect ?? back())->with('error', $message);
    }

    /**
     * Een foto voor de app. Het adres blijft de hele dag hetzelfde, zodat de bewaarde gegevens in de
     * app niet bij elke lading anders zijn (zie Api\App\WorkPackageController::photo).
     *
     * @return array{id: int, url: string, caption: string}
     */
    public static function photo(Photo $photo, string $fallback = 'Foto'): array
    {
        return [
            'id' => $photo->id,
            'url' => URL::temporarySignedRoute('api.app.photo', today()->addDays(2), $photo, absolute: false),
            'caption' => $photo->caption ?: $fallback,
        ];
    }

    /** Uren en aantallen zoals op de website: 7,5 en 8, zonder overbodige nullen. */
    public static function number(float|string|null $value, int $decimals = 2): string
    {
        return rtrim(rtrim(number_format((float) $value, $decimals, ',', '.'), '0'), ',');
    }
}
