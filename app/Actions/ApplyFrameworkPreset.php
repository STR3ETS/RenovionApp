<?php

namespace App\Actions;

use App\Enums\CalculationLineType;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetTerm;
use App\Models\FrameworkAgreement;
use App\Models\FrameworkPrice;
use Illuminate\Support\Facades\DB;

/**
 * De calculatie-preset van een raamovereenkomst op een begroting zetten.
 *
 * De vaste prijzen zijn verkoopprijzen: daarom komen ze als regel met
 * hoeveelheid nul in een eigen hoofdstuk, gaat de staart naar de opslagen uit
 * de overeenkomst (standaard nul) en wordt het betaalschema één termijn van
 * honderd procent na oplevering.
 */
class ApplyFrameworkPreset
{
    public const CHAPTER_CODE = 'RO';

    public const SOURCE = 'raamovereenkomst';

    public function handle(Budget $budget, FrameworkAgreement $agreement): int
    {
        return DB::transaction(function () use ($budget, $agreement) {
            $chapter = $budget->chapters()->firstOrCreate(
                ['code' => self::CHAPTER_CODE],
                ['name' => 'Vaste prijzen '.$agreement->number, 'position' => (int) $budget->chapters()->max('position') + 1],
            );

            $paragraph = $chapter->paragraphs()->firstOrCreate(
                ['code' => '.01'],
                ['name' => 'Volgens raamovereenkomst', 'position' => 0],
            );

            // Twee keer toepassen mag geen dubbele regels geven.
            $existing = $paragraph->lines()->where('source', self::SOURCE)->pluck('description')->all();
            $position = (int) $paragraph->lines()->max('position');
            $added = 0;

            foreach ($agreement->prices as $price) {
                if (in_array($price->description, $existing, true)) {
                    continue;
                }

                $paragraph->lines()->create([
                    'type' => BudgetLine::TYPE_LINE,
                    'description' => $price->description,
                    'quantity' => 0,
                    'unit' => $price->unit,
                    ...$this->priceColumns($price),
                    'source' => self::SOURCE,
                    'price_item_id' => $price->price_item_id,
                    'position' => ++$position,
                ]);

                $added++;
            }

            // Vaste prijzen zijn al verkoopprijzen: de staart volgt de overeenkomst.
            $budget->markups()->delete();
            $budget->markups()->createMany([
                ['name' => 'Algemene kosten', 'percentage' => $agreement->overhead_pct, 'position' => 0],
                ['name' => 'Winst en risico', 'percentage' => $agreement->profit_pct, 'position' => 1],
            ]);

            // Per opdracht in één keer afrekenen, na oplevering. Is er al een
            // termijn gefactureerd, dan blijven we van het schema af.
            if ($budget->terms()->where('status', '!=', BudgetTerm::STATUS_PLANNED)->doesntExist()) {
                $budget->terms()->delete();
                $budget->terms()->create([
                    'description' => 'Na oplevering',
                    'percentage' => 100,
                    'position' => 0,
                ]);
            }

            AuditLog::record($budget, 'preset raamovereenkomst toegepast', [], [
                'overeenkomst' => $agreement->number,
                'regels' => $added,
            ]);

            return $added;
        });
    }

    /**
     * De vaste prijs is een prijs per eenheid. Hij komt in de kolom van het
     * soort artikel, zodat het overzicht per kostensoort blijft kloppen.
     *
     * @return array<string, float>
     */
    private function priceColumns(FrameworkPrice $price): array
    {
        $column = match ($price->priceItem?->type) {
            CalculationLineType::Materiaal => 'material',
            CalculationLineType::Materieel => 'equipment',
            default => 'subcontracting',
        };

        return [$column => (float) $price->fixed_price];
    }
}
