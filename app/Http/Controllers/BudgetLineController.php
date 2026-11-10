<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetParagraph;
use App\Models\PriceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Begrotingsregels. Een regel kan uit de prijslijst komen (dan nemen we de prijs
 * en de eenheid over als momentopname) of met de hand worden ingevuld.
 */
class BudgetLineController extends Controller
{
    public function store(Request $request, BudgetParagraph $paragraph): RedirectResponse
    {
        $budget = $paragraph->chapter->budget;
        $this->assertDraft($budget);

        $validated = $request->validate([
            'price_item_id' => ['nullable', 'exists:price_items,id'],
            'description' => ['required_without:price_item_id', 'nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:24'],
            'unit' => ['nullable', 'string', 'max:12'],
            'type' => ['nullable', 'string', 'in:regel,tekst'],
        ]);

        $line = [
            'type' => $validated['type'] ?? BudgetLine::TYPE_LINE,
            'description' => $validated['description'] ?? '',
            'quantity' => BudgetLine::parseNumber($validated['quantity'] ?? 1),
            'unit' => $validated['unit'] ?? 'st',
            'position' => (int) $paragraph->lines()->max('position') + 1,
        ];

        // Uit de prijslijst: prijs en eenheid als momentopname overnemen, met de
        // kostensoort in het juiste vakje.
        if (isset($validated['price_item_id'])) {
            $item = PriceItem::findOrFail($validated['price_item_id']);
            $line = [
                ...$line,
                'description' => ($validated['description'] ?? null) ?: $item->name,
                'unit' => ($validated['unit'] ?? null) ?: $item->unit,
                'price_item_id' => $item->id,
                'source' => 'Prijslijst '.$item->code,
                ...$this->costFor($item),
            ];
        }

        $paragraph->lines()->create($line);

        return back()->with('success', 'Regel toegevoegd.');
    }

    public function update(Request $request, BudgetLine $line): RedirectResponse
    {
        $this->assertDraft($line->paragraph->chapter->budget);

        $validated = $request->validate([
            'description' => ['sometimes', 'string', 'max:255'],
            'quantity' => ['sometimes', 'string', 'max:24'],
            'unit' => ['sometimes', 'string', 'max:12'],
            'norm_hours' => ['sometimes', 'string', 'max:24'],
            'material' => ['sometimes', 'string', 'max:24'],
            'equipment' => ['sometimes', 'string', 'max:24'],
            'subcontracting' => ['sometimes', 'string', 'max:24'],
            'other' => ['sometimes', 'string', 'max:24'],
            'hourly_rate' => ['nullable', 'string', 'max:24'],
            'source' => ['nullable', 'string', 'max:120'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        // Alle getalvelden komen als Nederlandse tekst binnen: "1.234,56".
        foreach (['quantity', 'norm_hours', 'material', 'equipment', 'subcontracting', 'other'] as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = BudgetLine::parseNumber($validated[$field]);
            }
        }

        if (array_key_exists('hourly_rate', $validated)) {
            $parsed = BudgetLine::parseNumber($validated['hourly_rate']);
            $validated['hourly_rate'] = $parsed > 0 ? $parsed : null;
        }

        $line->update($validated);

        return back()->with('success', 'Regel bijgewerkt.');
    }

    public function destroy(BudgetLine $line): RedirectResponse
    {
        $this->assertDraft($line->paragraph->chapter->budget);

        $line->delete();

        return back()->with('success', 'Regel verwijderd.');
    }

    /**
     * De prijs uit de prijslijst in het juiste kostenvakje zetten, inclusief de
     * opslag die bij het artikel hoort.
     *
     * Arbeid krijgt één normuur per eenheid met de prijslijstprijs als tarief van
     * die regel: de prijslijst noteert een uurprijs, de begroting rekent met uren
     * maal tarief, en zo komt daar hetzelfde bedrag uit.
     *
     * @return array<string, float>
     */
    private function costFor(PriceItem $item): array
    {
        $price = round($item->effectivePrice() * (1 + (float) $item->surcharge_pct / 100), 2);

        return match ($item->type->value) {
            'arbeid' => ['norm_hours' => 1, 'hourly_rate' => $price],
            'materiaal' => ['material' => $price],
            'materieel' => ['equipment' => $price],
            'onderaannemer' => ['subcontracting' => $price],
            default => ['other' => $price],
        };
    }

    private function assertDraft(Budget $budget): void
    {
        abort_if(! $budget->isDraft(), 422, 'Deze begroting is definitief. Heropen hem eerst.');
    }
}
