<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Na een tijdelijk wachtwoord: zelf een eigen wachtwoord kiezen. Andere
 * sessies van hetzelfde account gaan daarbij dicht.
 */
class ChoosePasswordController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('home');
        }

        return view('auth.choose-password', ['user' => $request->user()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()],
        ], [
            'password.required' => 'Vul een nieuw wachtwoord in.',
            'password.confirmed' => 'De twee wachtwoorden zijn niet hetzelfde.',
            'password.min' => 'Kies een wachtwoord van minstens 10 tekens.',
            'password.letters' => 'Gebruik minstens één letter.',
            'password.numbers' => 'Gebruik minstens één cijfer.',
        ]);

        if (Hash::check($request->string('password')->value(), $user->password)) {
            return back()->withErrors(['password' => 'Kies een ander wachtwoord dan het tijdelijke.']);
        }

        $user->forceFill(['password' => $request->string('password')->value(), 'must_change_password' => false])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('status', 'Je eigen wachtwoord is ingesteld.');
    }
}
