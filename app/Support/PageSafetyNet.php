<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Het vangnet per pagina. Faalt een scherm, dan blijft het menu werken en
 * staat er "Deze pagina kon niet laden" met een knop om het opnieuw te
 * proberen, in plaats van een kale foutpagina waar je niet meer vanaf komt.
 *
 * Het vangnet mag zelf nooit de oorzaak van een fout worden: lukt het niet om
 * de nette pagina te maken, dan gaat het oorspronkelijke antwoord terug.
 */
final class PageSafetyNet
{
    /** @var array<int, array{0: string, 1: string}> status => [kop, uitleg] */
    private const MESSAGES = [
        403 => ['Deze pagina is niet voor jouw rol', 'Je hebt geen toegang tot dit scherm. Denk je dat dat niet klopt, vraag de directie dan om je rol na te kijken.'],
        404 => ['Deze pagina bestaat niet', 'Het adres klopt niet, of wat hier stond is verwijderd.'],
        419 => ['De pagina stond te lang open', 'Om veiligheidsredenen is het formulier verlopen. Laad de pagina opnieuw en probeer het nog eens.'],
        500 => ['Deze pagina kon niet laden', 'Er ging iets mis bij het ophalen van dit scherm. De rest van het systeem werkt gewoon door.'],
        503 => ['Even niet bereikbaar', 'Het systeem wordt bijgewerkt. Probeer het over een paar minuten opnieuw.'],
    ];

    public static function respond(Response $response, Throwable $exception, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (! self::applies($status, $request)) {
            return $response;
        }

        try {
            [$heading, $explanation] = self::MESSAGES[$status] ?? self::MESSAGES[500];

            return response()->view('errors.page', [
                'status' => $status,
                'heading' => $heading,
                'explanation' => $explanation,
                // Alleen opnieuw proberen waar dat zin heeft.
                'retry' => in_array($status, [419, 500, 503], true),
            ], $status);
        } catch (Throwable) {
            return $response;
        }
    }

    /**
     * Alleen voor gewone paginaverzoeken van iemand uit de interne omgeving.
     * In de ontwikkelomgeving blijft de uitgebreide foutpagina staan.
     */
    private static function applies(int $status, Request $request): bool
    {
        if (! array_key_exists($status, self::MESSAGES) || $request->expectsJson() || $request->is('api/*')) {
            return false;
        }

        if ($status >= 500 && config('app.debug')) {
            return false;
        }

        try {
            return Auth::check() && Auth::user()->can('internal');
        } catch (Throwable) {
            return false;
        }
    }
}
