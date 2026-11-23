<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wie met een tijdelijk wachtwoord inlogt, kiest eerst een eigen wachtwoord
 * voordat het systeem opent.
 */
class EnsurePasswordChosen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs('password.choose', 'password.choose.store', 'logout')) {
            if ($request->is('os/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Kies eerst een eigen wachtwoord.'], 409);
            }

            return redirect()->route('password.choose');
        }

        return $next($request);
    }
}
