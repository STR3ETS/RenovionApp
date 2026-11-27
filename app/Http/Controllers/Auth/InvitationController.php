<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Os\Invites;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * De link uit een uitnodiging: wie hem opent, is ingelogd en kiest meteen
 * een eigen wachtwoord. Daarna is de link verbruikt.
 */
class InvitationController extends Controller
{
    public function show(Request $request, int $user): RedirectResponse
    {
        $account = User::query()->find($user);

        if (! $account || ! Invites::valid($request, $account)) {
            // Al een eigen wachtwoord gekozen: dan is het gewoon inloggen.
            $used = $account && $account->active && ! $account->must_change_password && $request->hasValidRelativeSignature();

            if ($used && Auth::id() === $account->id) {
                return redirect()->route('home');
            }

            return redirect()->route('login')
                ->withInput($used ? ['email' => $account->email] : [])
                ->withErrors(['email' => $used
                    ? 'Deze uitnodiging is al gebruikt. Log in met je e-mailadres en je eigen wachtwoord.'
                    : 'Deze link is verlopen of niet meer geldig. Vraag bij Renovion Groep BV een nieuwe uitnodiging aan.']);
        }

        if (Auth::check() && Auth::id() !== $account->id) {
            // Iemand anders was op dit apparaat ingelogd: die sessie gaat dicht.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::login($account);
        $request->session()->regenerate();
        $request->session()->put('via_uitnodiging', true);

        return redirect()->route('password.choose');
    }
}
