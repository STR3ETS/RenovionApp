<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\CalculationLineType;
use App\Http\Controllers\Controller;
use App\Models\PriceItem;
use Illuminate\Http\JsonResponse;

/**
 * De prijslijst in de app (price-items/index): alle artikelen in één keer, zoeken en het typefilter gebeuren
 * op de telefoon zelf (ook zonder verbinding). Toevoegen, bewerken en aan/uit zetten gaan via de
 * PriceListController van de website, die JSON geeft aan de app.
 */
class PriceListController extends Controller
{
    public function index(): JsonResponse
    {
        $items = PriceItem::query()->orderBy('type')->orderBy('name')->get();
        $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

        return response()->json([
            'count' => $items->count(),
            'types' => array_map(fn (CalculationLineType $type) => ['value' => $type->value, 'label' => $type->label()], CalculationLineType::cases()),
            'units' => ['m2', 'm1', 'uur', 'stuk', 'post'],
            'items' => $items->map(fn (PriceItem $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'sub' => $item->source.' ('.$item->edition.')'
                    .((float) $item->surcharge_pct > 0 ? ', opslag '.$number($item->surcharge_pct).'%' : ''),
                'type' => [
                    'value' => $item->type->value,
                    'label' => $item->type->label(),
                    'classes' => $item->type->appBadgeClasses(),
                ],
                'unit' => $item->unit,
                'price' => $item->effectivePrice(),
                'unit_price' => (float) $item->unit_price,
                'surcharge_pct' => (float) $item->surcharge_pct,
                'active' => (bool) $item->active,
            ]),
        ]);
    }
}
