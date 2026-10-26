<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Services\CalculationAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nova stelt offerteposten voor: de gebruiker beschrijft het werk, Nova
 * zoekt de passende artikelen in de prijslijst (kostendatabase) en de
 * gebruiker controleert de regels in de editor voordat er iets wordt
 * opgeslagen (briefing: AI-output is altijd een concept).
 */
class QuoteLineProposalController extends Controller
{
    private const UNITS = ['m2' => 'm²', 'm1' => 'm¹', 'uur' => 'uur', 'stuk' => 'stuks', 'stuks' => 'stuks', 'post' => 'post', 'm²' => 'm²', 'm¹' => 'm¹'];

    public function __invoke(Request $request, Quote $quote, CalculationAssistant $assistant): JsonResponse
    {
        if ($quote->status !== QuoteStatus::Concept) {
            return response()->json(['ok' => false, 'error' => 'Offerte is niet meer in concept.'], 422);
        }

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:4000'],
        ], [], ['description' => 'omschrijving']);

        if (! $assistant->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'De AI-assistent is niet geconfigureerd.'], 422);
        }

        $result = $assistant->proposeLines($validated['description'], []);

        if (isset($result['error'])) {
            return response()->json(['ok' => false, 'error' => $result['error']], 422);
        }

        $lines = collect($result['lines'])
            ->map(fn (array $line) => [
                'description' => $line['description'],
                'quantity' => (float) $line['quantity'],
                'unit' => self::UNITS[$line['unit'] ?? 'post'] ?? 'post',
                'unit_price' => round((float) $line['unit_price'] * (1 + ((float) ($line['surcharge_pct'] ?? 0)) / 100), 2),
                'vat_rate' => 21,
                'is_estimate' => ($line['type'] ?? '') === 'stelpost',
            ])
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'lines' => $lines,
            'note' => $result['note'] ?? null,
        ]);
    }
}
