<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Weergave van de iOS/Android-app: licht, donker of volgens de telefoon. Zowel het zijmenu in de app
 * (api/app/weergave) als dat van de serverpagina's in de app (weergave) slaat het hier op.
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
