<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Licht of donker thema per gebruiker. "auto" volgt het besturingssysteem.
 * Klantgerichte en afdrukbare schermen blijven altijd licht.
 */
class ThemePreferenceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(User::THEMES)],
        ]);

        $request->user()->update(['theme' => $data['theme']]);

        return back();
    }
}
