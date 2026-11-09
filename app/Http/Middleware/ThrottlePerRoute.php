<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * "throttle:30,1" zonder naam telt in Laravel per gebruiker (of per IP) één teller voor álle routes met zo'n
 * limiet. Dan telden de verzoeken van de app (schermen vooraf laden, ongelezen chat) mee voor bijvoorbeeld
 * Nova, en gaf een drukke minuut "Even rustig aan" op iets wat je net één keer deed. Deze versie geeft elke
 * route een eigen teller. Benoemde limieten (throttle:login) blijven zoals ze zijn.
 */
class ThrottlePerRoute extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        if (is_string($maxAttempts) && func_num_args() === 3 && $this->limiter->limiter($maxAttempts) !== null) {
            return parent::handle($request, $next, $maxAttempts);
        }

        if ($prefix === '') {
            $route = $request->route();
            $prefix = ($route?->getName() ?? $route?->uri() ?? '').'|'.$maxAttempts.'|';
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }
}
