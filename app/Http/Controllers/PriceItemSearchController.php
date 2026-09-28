<?php

namespace App\Http\Controllers;

use App\Models\PriceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Zoekt in de kostendatabase voor de regel-picker op de calculatiepagina.
 */
class PriceItemSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $q = trim((string) $request->query('q'));
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';

        $items = PriceItem::active()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', $like))
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn (PriceItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type->value,
                'type_label' => $item->type->label(),
                'unit' => $item->unit,
                'price' => $item->effectivePrice(),
                'surcharge_pct' => (float) $item->surcharge_pct,
                'source' => $item->source.' ('.$item->edition.')',
            ]);

        return response()->json(['items' => $items]);
    }
}
