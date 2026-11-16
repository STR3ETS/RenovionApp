<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalculationLineRequest;
use App\Models\Calculation;
use App\Models\CalculationLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Regels van een calculatie. De app (mobile/app/screens/calculaties.*) gebruikt dezelfde acties en krijgt JSON.
 */
class CalculationLineController extends Controller
{
    public function store(StoreCalculationLineRequest $request, Calculation $calculation): RedirectResponse|JsonResponse
    {
        abort_if($calculation->isLocked(), 403, 'Deze calculatie is definitief; heropen hem om regels te wijzigen.');

        $calculation->addLine($request->validated());

        return $this->done($request, $calculation, 'Regel toegevoegd.');
    }

    public function update(Request $request, Calculation $calculation, CalculationLine $line): RedirectResponse|JsonResponse
    {
        abort_if($calculation->isLocked(), 403, 'Deze calculatie is definitief; heropen hem om regels te wijzigen.');

        $line->update($request->validate([
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100000'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:1000000'],
            'surcharge_pct' => ['sometimes', 'numeric', 'min:0', 'max:500'],
        ]));

        return $this->done($request, $calculation, 'Regel bijgewerkt.');
    }

    public function destroy(Request $request, Calculation $calculation, CalculationLine $line): RedirectResponse|JsonResponse
    {
        abort_if($calculation->isLocked(), 403, 'Deze calculatie is definitief; heropen hem om regels te wijzigen.');

        $line->delete();

        return $this->done($request, $calculation, 'Regel verwijderd.');
    }

    private function done(Request $request, Calculation $calculation, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('calculations.show', $calculation)->with('success', $message);
    }
}
