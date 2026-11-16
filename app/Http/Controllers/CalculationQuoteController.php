<?php

namespace App\Http\Controllers;

use App\Actions\CreateQuoteFromCalculation;
use App\Models\Calculation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Van calculatie naar offerte met één actie (briefing §5). De app krijgt JSON met de paden van de nieuwe offerte.
 */
class CalculationQuoteController extends Controller
{
    public function __invoke(Request $request, Calculation $calculation, CreateQuoteFromCalculation $action): RedirectResponse|JsonResponse
    {
        if ($calculation->customer_id === null) {
            return $this->refuse($request, $calculation, 'Koppel eerst een klant aan deze calculatie voordat je een offerte maakt.');
        }

        if (! $calculation->isLocked()) {
            return $this->refuse($request, $calculation, 'Maak de calculatie eerst definitief - de offerte legt vast met welke prijsset hij is gemaakt.');
        }

        $quote = $action->handle($calculation);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $quote->id,
                'number' => $quote->number,
                'path' => route('quotes.show', $quote, false),
                'builder' => route('quotes.builder', $quote, false),
            ], 201);
        }

        // Direct de builder in: daar controleer je de teksten en bouw je het document af.
        return redirect()->route('quotes.builder', $quote);
    }

    private function refuse(Request $request, Calculation $calculation, string $message): RedirectResponse|JsonResponse
    {
        // De app krijgt de zin zonder gedachtestreepje (huisstijl van de app-teksten).
        return $request->expectsJson()
            ? response()->json(['message' => str_replace(' - ', ': ', $message)], 422)
            : redirect()->route('calculations.show', $calculation)->with('error', $message);
    }
}
