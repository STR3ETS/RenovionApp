<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Notifications\TestPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Instellingen > Meldingen: per soort pushmelding aan/uit (briefing §19) en een testmelding.
 */
class NotificationSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.notifications', [
            'types' => NotificationType::forUser($request->user()),
            'devices' => $request->user()->deviceTokens()->count(),
        ]);
    }

    /**
     * Eén schakelaar omzetten; slaat direct op (geen opslaan-knop).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_map(fn (NotificationType $type) => $type->value, NotificationType::forUser($request->user())))],
            'enabled' => ['required', 'boolean'],
        ]);

        // Alleen uitgezette soorten opslaan; nieuwe soorten staan dan vanzelf aan.
        $preferences = collect($request->user()->notification_preferences ?? [])
            ->except($validated['type'])
            ->when(! $validated['enabled'], fn ($preferences) => $preferences->put($validated['type'], false))
            ->all();

        $request->user()->forceFill(['notification_preferences' => $preferences ?: null])->save();

        return response()->json(['type' => $validated['type'], 'enabled' => (bool) $validated['enabled']]);
    }

    /**
     * Animaties aan of uit voor deze gebruiker; slaat direct op.
     */
    public function updateMotion(Request $request): JsonResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        $request->user()->forceFill(['motion' => $validated['enabled']])->save();

        return response()->json(['enabled' => (bool) $validated['enabled']]);
    }

    public function test(Request $request): RedirectResponse
    {
        if ($request->user()->deviceTokens()->doesntExist()) {
            return back()->with('error', 'Er is nog geen telefoon gekoppeld. Log in de Renovion-app in en sta meldingen toe.');
        }

        $request->user()->notify(new TestPush);

        return back()->with('success', 'Testmelding verstuurd. Hij verschijnt binnen een paar seconden op je telefoon.');
    }
}
