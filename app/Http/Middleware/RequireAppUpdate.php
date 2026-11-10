<?php

namespace App\Http\Middleware;

use App\Support\AppRelease;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Een app die ouder is dan minimum_build (mobile/release.json) kan niet verder: de app-API antwoordt
 * met 426 en een uitleg, een serverpagina in de app toont het scherm "Update nodig". De website in een
 * gewone browser merkt hier niets van.
 */
class RequireAppUpdate
{
    /** Wat ook een verouderde app nog mag: uitloggen en de privacyverklaring lezen. */
    private const ALWAYS = ['logout', 'privacy', 'api.app.logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $build = AppRelease::buildOf($request);

        if (! AppRelease::mustUpdate($build) || $request->routeIs(...self::ALWAYS)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(AppRelease::payload($request), 426);
        }

        return response()->view('app-update', ['update' => AppRelease::payload($request)['update']], 426);
    }
}
