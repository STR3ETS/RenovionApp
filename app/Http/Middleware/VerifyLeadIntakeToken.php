<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyLeadIntakeToken
{
    /**
     * Controleer het intake-token waarmee de Renovion-website aanvragen mag aanleveren.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('renovion.lead_intake_token');

        if (blank($token) || ! hash_equals((string) $token, (string) $request->header('X-Intake-Token'))) {
            abort(401, 'Ongeldig intake-token.');
        }

        return $next($request);
    }
}
