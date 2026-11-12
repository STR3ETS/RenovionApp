<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\TestPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Instellingen in de app (scherm "instellingen"): pushmeldingen per soort, weergave en animaties. De app
 * verzamelt wijzigingen en stuurt ze in één keer als je het scherm of het zijmenu verlaat, dus elk veld is
 * optioneel en alleen wat meekomt, verandert. Het antwoord is altijd de hele stand.
 */
class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $types = array_map(fn (NotificationType $type) => $type->value, NotificationType::forUser($user));

        $validated = $request->validate([
            'theme' => ['sometimes', Rule::in(User::APP_THEMES)],
            'motion' => ['sometimes', 'boolean'],
            'notifications' => ['sometimes', 'array:'.implode(',', $types)],
            'notifications.*' => ['boolean'],
        ]);

        if (isset($validated['notifications'])) {
            $user->setPushPreferences(array_map(fn ($on) => (bool) $on, $validated['notifications']));
        }
        if (array_key_exists('theme', $validated)) {
            $user->app_theme = $validated['theme'];
        }
        if (array_key_exists('motion', $validated)) {
            $user->motion_enabled = (bool) $validated['motion'];
        }
        $user->save();

        return response()->json($this->payload($user));
    }

    public function test(Request $request): JsonResponse
    {
        if ($request->user()->deviceTokens()->doesntExist()) {
            return response()->json(['message' => 'Er is nog geen telefoon gekoppeld. Sta meldingen toe voor Renovion en probeer het opnieuw.'], 422);
        }

        $request->user()->notify(new TestPush);

        return response()->json(['message' => 'Testmelding verstuurd. Hij verschijnt binnen een paar seconden.']);
    }

    /** @return array<string, mixed> */
    private function payload(User $user): array
    {
        return [
            'notifications' => array_map(fn (NotificationType $type) => [
                'type' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'icon' => $type->icon(),
                'enabled' => $user->wantsPush($type),
            ], NotificationType::forUser($user)),
            'theme' => $user->app_theme ?? 'licht',
            'motion' => $user->motion_enabled !== false,
            'devices' => $user->deviceTokens()->count(),
        ];
    }
}
