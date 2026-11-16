<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\CalculationLineType;
use App\Http\Controllers\CalculationController as WebCalculationController;
use App\Http\Controllers\Controller;
use App\Models\Calculation;
use App\Models\CalculationLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Calculaties in de app (calculations/index en calculations/show). Regels, opslagen, status, verwijderen en
 * "offerte maken" gaan via de controllers van de website (zelfde validatie en regels), die JSON geven aan de app.
 */
class CalculationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['pagina' => ['nullable', 'integer', 'min:1']]);

        $calculations = Calculation::with(['customer', 'lines'])
            ->latest()
            ->paginate(25, page: $request->integer('pagina', 1));

        return response()->json([
            'total' => $calculations->total(),
            'page' => $calculations->currentPage(),
            'more' => $calculations->hasMorePages(),
            'calculations' => $calculations->getCollection()->map(fn (Calculation $calculation) => [
                'id' => $calculation->id,
                'title' => $calculation->title,
                'sub' => ($calculation->customer?->name ?? 'Geen klant gekoppeld')
                    .', '.$calculation->lines->count().' regels'
                    .', '.$calculation->created_at->translatedFormat('j M Y'),
                'total' => $calculation->totalExcl(),
                'status' => ['value' => $calculation->status->value, 'label' => $calculation->status->label(), 'classes' => $calculation->status->appBadgeClasses()],
                'path' => route('calculations.show', $calculation, false),
            ]),
            'links' => ['create' => route('calculations.create', [], false)],
        ]);
    }

    public function show(Calculation $calculation): JsonResponse
    {
        $calculation->load(['customer', 'lead', 'lines', 'creator']);
        $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

        return response()->json([
            'id' => $calculation->id,
            'title' => $calculation->title,
            'subtitle' => ($calculation->customer?->name ?? 'Geen klant').', aangemaakt '.$calculation->created_at->translatedFormat('j M Y')
                .($calculation->creator ? ' door '.$calculation->creator->name : ''),
            'status' => ['value' => $calculation->status->value, 'label' => $calculation->status->label(), 'classes' => $calculation->status->appBadgeClasses()],
            'locked' => $calculation->isLocked(),
            'has_customer' => $calculation->customer_id !== null,
            'quotes' => (bool) config('renovion.modules.quotes'),
            'description' => $calculation->description,
            'lines' => $calculation->lines->map(fn (CalculationLine $line) => [
                'id' => $line->id,
                'description' => $line->description,
                'type' => ['label' => $line->type->label(), 'classes' => $line->type->appBadgeClasses()],
                'source' => $line->price_source ? "{$line->price_source} ({$line->price_edition})" : null,
                'quantity' => (float) $line->quantity,
                'quantity_label' => $number($line->quantity),
                'unit' => $line->unit,
                'unit_price' => (float) $line->unit_price,
                'surcharge' => (float) $line->surcharge_pct > 0 ? $number($line->surcharge_pct).'%' : null,
                'total' => $line->total(),
            ]),
            'totals' => [
                ['label' => 'Subtotaal regels', 'value' => $calculation->subtotal()],
                ['label' => 'Risico/onvoorzien ('.$number($calculation->risk_pct).'%)', 'value' => $calculation->riskAmount()],
                ['label' => 'Marge ('.$number($calculation->margin_pct).'%)', 'value' => $calculation->marginAmount()],
                ['label' => 'Totaal excl. btw', 'value' => $calculation->totalExcl(), 'strong' => true],
                ['label' => 'Btw ('.$number($calculation->vat_pct).'%)', 'value' => $calculation->vatAmount()],
                ['label' => 'Totaal incl. btw', 'value' => $calculation->totalIncl(), 'strong' => true, 'big' => true],
            ],
            'settings' => [
                'risk_pct' => (float) $calculation->risk_pct,
                'margin_pct' => (float) $calculation->margin_pct,
                'vat_pct' => (float) $calculation->vat_pct,
            ],
            'types' => array_map(fn (CalculationLineType $type) => ['value' => $type->value, 'label' => $type->label()], CalculationLineType::cases()),
            'links' => [
                'lead' => $calculation->lead ? route('leads.show', $calculation->lead, false) : null,
                'customer' => $calculation->customer ? route('customers.show', $calculation->customer, false) : null,
            ],
        ]);
    }

    /**
     * Opslagen en btw, of de status (definitief maken, heropenen): via CalculationController@update van de
     * website, zodat validatie en auditlog gelijk blijven. Het antwoord is de verse calculatie.
     */
    public function update(Request $request, Calculation $calculation, WebCalculationController $web): JsonResponse
    {
        $web->update($request, $calculation);

        return $this->show($calculation->fresh());
    }
}
