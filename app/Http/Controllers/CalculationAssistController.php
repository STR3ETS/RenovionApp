<?php

namespace App\Http\Controllers;

use App\Services\CalculationAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI-voorstel voor calculatieregels (briefing §5): de regels gaan terug
 * naar de browser en worden pas opgeslagen nadat de gebruiker ze heeft
 * gecontroleerd en bevestigd.
 */
class CalculationAssistController extends Controller
{
    public function __invoke(Request $request, CalculationAssistant $assistant): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:10000'],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['string', 'max:100'],
        ]);

        if (! $assistant->isConfigured()) {
            return response()->json(['message' => 'De AI-assistent is niet geconfigureerd (ANTHROPIC_API_KEY ontbreekt).'], 422);
        }

        $result = $assistant->proposeLines($validated['description'], $validated['sources'] ?? []);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json($result);
    }
}
