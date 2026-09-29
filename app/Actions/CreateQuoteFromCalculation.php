<?php

namespace App\Actions;

use App\Enums\CalculationLineType;
use App\Enums\LeadStatus;
use App\Enums\QuoteStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Calculation;
use App\Models\CalculationLine;
use App\Models\Quote;
use App\Support\QuoteBlockRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Van calculatie naar offerte met één actie (briefing §5): de klant ziet
 * geen interne kostregels — de offerte toont commerciële posten waarin
 * onvoorzien en marge zijn verdeeld. Stelposten blijven herkenbaar apart.
 */
class CreateQuoteFromCalculation
{
    public function handle(Calculation $calculation): Quote
    {
        return DB::transaction(function () use ($calculation) {
            $calculation->loadMissing(['lines', 'customer', 'lead']);

            $quote = Quote::create([
                'number' => Quote::nextNumber(),
                'customer_id' => $calculation->customer_id,
                'lead_id' => $calculation->lead_id,
                'calculation_id' => $calculation->id,
                'status' => QuoteStatus::Concept,
                'valid_until' => now()->addDays(30)->toDateString(),
            ]);

            $quote->update(['blocks' => QuoteBlockRegistry::forNewQuote($quote)]);

            $this->buildCommercialLines($quote, $calculation);

            if ($quote->lead !== null && ! in_array($quote->lead->status, [LeadStatus::Akkoord, LeadStatus::Project], true)) {
                $quote->lead->update(['status' => LeadStatus::Offerte]);
            }

            $quote->customer->recordEvent(
                TimelineEventType::Offerte,
                "Offerte {$quote->number} aangemaakt vanuit calculatie \"{$calculation->title}\"",
                null,
                $quote,
            );

            AuditLog::record($quote, 'aangemaakt', [], [
                'calculatie' => $calculation->title,
                'total' => (string) $quote->total,
            ]);

            return $quote;
        });
    }

    /**
     * Groepeer interne kostregels tot commerciële posten. Onvoorzien en marge
     * worden naar rato verdeeld over de posten (niet over stelposten, zodat
     * die herkenbaar blijven voor de klant).
     */
    private function buildCommercialLines(Quote $quote, Calculation $calculation): void
    {
        $labels = [
            CalculationLineType::Arbeid->value => 'Arbeid en montage',
            CalculationLineType::Materiaal->value => 'Materialen',
            CalculationLineType::Onderaannemer->value => 'Installaties en onderaannemers',
            CalculationLineType::Materieel->value => 'Materieel en voorzieningen',
        ];

        $stelposten = $calculation->lines->filter(fn (CalculationLine $line) => $line->type === CalculationLineType::Stelpost);
        $overige = $calculation->lines->reject(fn (CalculationLine $line) => $line->type === CalculationLineType::Stelpost);

        $stelpostSum = round($stelposten->sum(fn (CalculationLine $line) => $line->total()), 2);
        $overigeSum = round($overige->sum(fn (CalculationLine $line) => $line->total()), 2);
        $verdeelbaar = $calculation->totalExcl() - $stelpostSum;
        $factor = $overigeSum > 0.0 ? $verdeelbaar / $overigeSum : 1.0;

        $position = 0;
        $verdeeld = 0.0;
        $groups = $overige->groupBy(fn (CalculationLine $line) => $line->type->value);

        foreach ($groups as $type => $lines) {
            $amount = round($lines->sum(fn (CalculationLine $line) => $line->total()) * $factor, 2);

            // Vang afrondingsverschillen op in de laatste post, zodat het
            // offertetotaal exact gelijk is aan het calculatietotaal.
            if ($groups->keys()->last() === $type) {
                $amount = round($verdeelbaar - $verdeeld, 2);
            }
            $verdeeld = round($verdeeld + $amount, 2);

            $quote->lines()->create([
                'description' => $labels[$type] ?? ucfirst($type),
                'quantity' => 1,
                'unit' => 'post',
                'unit_price' => $amount,
                'vat_rate' => (float) $calculation->vat_pct,
                'total' => $amount,
                'is_estimate' => false,
                'position' => $position++,
            ]);
        }

        foreach ($stelposten as $stelpost) {
            $amount = round($stelpost->total(), 2);

            $quote->lines()->create([
                'description' => $stelpost->description,
                'quantity' => 1,
                'unit' => 'post',
                'unit_price' => $amount,
                'vat_rate' => (float) $calculation->vat_pct,
                'total' => $amount,
                'is_estimate' => true,
                'position' => $position++,
            ]);
        }

        $quote->recalculateTotals();
    }
}
