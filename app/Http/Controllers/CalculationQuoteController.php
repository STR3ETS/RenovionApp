<?php

namespace App\Http\Controllers;

use App\Actions\CreateQuoteFromCalculation;
use App\Models\Calculation;
use Illuminate\Http\RedirectResponse;

/**
 * Van calculatie naar offerte met één actie (briefing §5).
 */
class CalculationQuoteController extends Controller
{
    public function __invoke(Calculation $calculation, CreateQuoteFromCalculation $action): RedirectResponse
    {
        if ($calculation->customer_id === null) {
            return redirect()->route('calculations.show', $calculation)
                ->with('error', 'Koppel eerst een klant aan deze calculatie voordat je een offerte maakt.');
        }

        if (! $calculation->isLocked()) {
            return redirect()->route('calculations.show', $calculation)
                ->with('error', 'Maak de calculatie eerst definitief — de offerte legt vast met welke prijsset hij is gemaakt.');
        }

        $quote = $action->handle($calculation);

        return redirect()->route('quotes.show', $quote)
            ->with('success', "Offerte {$quote->number} aangemaakt vanuit de calculatie. Controleer de teksten en verstuur hem daarna.");
    }
}
