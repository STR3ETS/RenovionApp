<?php

namespace App\Http\Controllers;

use App\Enums\CalculationLineType;
use App\Models\PriceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * De prijslijst: beheer van de kostendatabase (briefing §5). Artikelen
 * krijgen automatisch een artikelnummer en worden door Nova gebruikt om
 * calculatie- en offerteregels voor te stellen.
 */
class PriceListController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(CalculationLineType::class)],
        ]);

        $q = trim((string) $request->query('q'));
        $type = $request->filled('type') ? CalculationLineType::from($request->query('type')) : null;
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';

        $items = PriceItem::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)))
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('price-items.index', [
            'items' => $items,
            'q' => $q,
            'type' => $type,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $item = PriceItem::create([
            ...$validated,
            'source' => 'Renovion praktijkprijzen',
            'edition' => (string) now()->year,
            'index_factor' => 1,
            'active' => true,
            'last_checked_at' => today(),
        ]);

        return redirect()->route('price-items.index', $request->only(['q', 'type']))
            ->with('success', "Artikel {$item->code} toegevoegd aan de prijslijst.");
    }

    public function update(Request $request, PriceItem $priceItem): RedirectResponse
    {
        // Alleen aan/uit zetten
        if ($request->boolean('toggle')) {
            $priceItem->update(['active' => ! $priceItem->active]);

            return redirect()->route('price-items.index', $request->only(['q', 'type']))
                ->with('success', $priceItem->active ? "{$priceItem->code} staat weer aan." : "{$priceItem->code} staat uit en wordt niet meer voorgesteld.");
        }

        $priceItem->update([
            ...$this->validated($request),
            'last_checked_at' => today(),
        ]);

        return redirect()->route('price-items.index', $request->only(['q', 'type']))
            ->with('success', "Artikel {$priceItem->code} bijgewerkt.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CalculationLineType::class)],
            'unit' => ['required', 'string', 'max:10'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'surcharge_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [], [
            'name' => 'naam',
            'type' => 'type',
            'unit' => 'eenheid',
            'unit_price' => 'prijs',
            'surcharge_pct' => 'opslag',
        ]);
    }
}
