<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Animaties aan of uit per gebruiker (briefing hoofdstuk 6). Het
 * besturingssysteem wint altijd: staat prefers-reduced-motion aan, dan
 * beweegt er sowieso niets.
 */
class MotionPreferenceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->update(['motion_enabled' => ! $user->motion_enabled]);

        return back()->with('success', $user->motion_enabled
            ? 'Animaties staan weer aan.'
            : 'Animaties staan uit.');
    }
}
