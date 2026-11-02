<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * De app meldt hier het Firebase-token van de telefoon aan (resources/js/native.js), zodat
 * pushmeldingen bij de ingelogde gebruiker aankomen.
 */
class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:android,ios'],
        ]);

        // Hetzelfde toestel met een andere gebruiker: het token verhuist mee.
        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            ['user_id' => $request->user()->id, 'platform' => $validated['platform']],
        )->touch();

        return response()->json(['registered' => true]);
    }
}
