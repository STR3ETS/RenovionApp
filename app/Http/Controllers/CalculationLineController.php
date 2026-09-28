<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalculationLineRequest;
use App\Models\Calculation;
use App\Models\CalculationLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CalculationLineController extends Controller
{
    public function store(StoreCalculationLineRequest $request, Calculation $calculation): RedirectResponse
    {
        abort_if($calculation->isLocked(), 403, 'Deze calculatie is definitief; heropen hem om regels te wijzigen.');

        $calculation->addLine($request->validated());

        return redirect()->route('calculations.show', $calculation)->with('success', 'Regel toegevoegd.');
    }

    public function update(Request $request, Calculation $calculation, CalculationLine $line): RedirectResponse
    {
        abort_if($calculation->isLocked(), 403, 'Deze calculatie is definitief; heropen hem om regels te wijzigen.');

        $line->update($request->validate([
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100000'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:1000000'],
            'surcharge_pct' => ['sometimes', 'numeric', 'min:0', 'max:500'],
        ]));

        return redirect()->route('calculations.show', $calculation)->with('success', 'Regel bijgewerkt.');
    }

    public function destroy(Calculation $calculation, CalculationLine $line): RedirectResponse
    {
        abort_if($calculation->isLocked(), 403, 'Deze calculatie is definitief; heropen hem om regels te wijzigen.');

        $line->delete();

        return redirect()->route('calculations.show', $calculation)->with('success', 'Regel verwijderd.');
    }
}
