<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Weergave van de iOS/Android-app: licht, donker of volgens de telefoon (api/app/weergave). Alleen nog voor
 * apps tot en met build 4, die de keuze in het zijmenu hadden; vanaf build 5 gaat het via Instellingen
 * (Api\App\SettingsController).
 */
class AppThemeController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(['theme' => ['required', Rule::in(User::APP_THEMES)]]);

        $request->user()->forceFill(['app_theme' => $validated['theme']])->save();

        return response()->json(['theme' => $validated['theme']]);
    }
}
