<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Van de iOS/Android-app naar een serverpagina: de eenmalige code (Api\App\SessionController::webCode)
 * logt de gebruiker in de webomgeving in. De sessie onthoudt waar de app draait, zodat de schil
 * (partials/app) voor Vandaag en Projecten terugverwijst naar de schermen in de app.
 */
class AppWebSessionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = Cache::pull('app-web-code:'.$request->string('code'));

        if ($data === null) {
            return redirect()->route('login');
        }

        Auth::guard('web')->loginUsingId($data['user'], remember: true);
        $request->session()->regenerate();
        $request->session()->put('app_bundle', $data['terug']);
        // Buildnummer van de app, voor de verplichte update op serverpagina's (RequireAppUpdate).
        $request->session()->put('app_build', $data['build'] ?? 0);

        return redirect($data['naar']);
    }
}
