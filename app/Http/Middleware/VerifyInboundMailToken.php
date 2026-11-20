<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInboundMailToken
{
    /**
     * Controleer het token waarmee de maildienst berichten voor de aanvraagmail
     * mag afleveren. Zonder ingesteld token staat de ingang dicht.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('renovion.inbound_mail_token');
        $given = $request->header('X-Inbound-Token') ?? $request->query('token');

        if (blank($token) || ! is_string($given) || ! hash_equals((string) $token, $given)) {
            abort(401, 'Ongeldig token voor de aanvraagmail.');
        }

        return $next($request);
    }
}
