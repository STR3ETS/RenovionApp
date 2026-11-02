<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\BudgetOption;

/**
 * De doorrekening van een begroting, zoals de begrotingstool van Renovion die
 * maakt. Alles staat in één waardeobject zodat een scherm nooit zelf hoeft te
 * rekenen en een rapport dezelfde getallen krijgt.
 *
 * De keten is: directe kosten per regel, opgeteld per hoofdstuk, dan de staart
 * er cumulatief over, dan btw. Marge is het deel van de aanneemsom dat boven de
 * directe kosten uitkomt; opslag is datzelfde bedrag gedeeld door de kosten.
 */
class BudgetTotals
{
    /**
     * @param  array<string, float>  $categories  Arbeid, materiaal, materieel, onderaanneming, overig.
     * @param  list<array{code: string, name: string, total: float}>  $chapters
     * @param  list<array{name: string, percentage: float, base: float, amount: float}>  $markups
     */
    public function __construct(
        public readonly array $categories,
        public readonly array $chapters,
        public readonly array $markups,
        public readonly float $direct,
        public readonly float $markupTotal,
        public readonly float $totalExcludingVat,
        public readonly float $vat,
        public readonly float $vatPercentage,
        public readonly float $totalIncludingVat,
        public readonly float $hours,
        public readonly int $lineCount,
        public readonly int $incompleteLines,
        public readonly float $approvedExtras,
        public readonly float $openOptions,
        public readonly float $markupFactor,
        public readonly float $margin,
        public readonly float $surcharge,
        public readonly ?float $pricePerSquareMetre,
    ) {}

    public static function for(Budget $budget): self
    {
        $budget->loadMissing(['chapters.paragraphs.lines', 'markups', 'options']);

        $rate = (float) $budget->hourly_rate;
        $categories = ['arbeid' => 0.0, 'materiaal' => 0.0, 'materieel' => 0.0, 'onderaanneming' => 0.0, 'overig' => 0.0];
        $chapters = [];
        $hours = 0.0;
        $lineCount = 0;
        $incomplete = 0;

        foreach ($budget->chapters as $chapter) {
            $chapterTotal = 0.0;

            foreach ($chapter->paragraphs as $paragraph) {
                foreach ($paragraph->lines as $line) {
                    if (! $line->countsTowardsTotal()) {
                        continue;
                    }

                    $costs = $line->costs($rate);

                    $chapterTotal += $costs['totaal'];
                    $hours += $costs['uren'];
                    $lineCount++;

                    if ($line->isIncomplete()) {
                        $incomplete++;
                    }

                    foreach ($categories as $key => $value) {
                        $categories[$key] = $value + $costs[$key];
                    }
                }
            }

            $chapters[] = [
                'code' => $chapter->code,
                'name' => $chapter->name,
                'total' => round($chapterTotal, 2),
            ];
        }

        $direct = array_sum($categories);

        // De staart rekent cumulatief: winst en risico gaat over de directe
        // kosten plus de algemene kosten, niet alleen over de directe kosten.
        $cumulative = 0.0;
        $markups = [];

        foreach ($budget->markups as $markup) {
            $base = $direct + $cumulative;
            $amount = $base * (float) $markup->percentage / 100;
            $cumulative += $amount;

            $markups[] = [
                'name' => $markup->name,
                'percentage' => (float) $markup->percentage,
                'base' => round($base, 2),
                'amount' => round($amount, 2),
            ];
        }

        $totalExcl = round($direct + $cumulative, 2);
        $vatPct = (float) $budget->vat_pct;
        $vat = round($totalExcl * $vatPct / 100, 2);

        // Opties rekenen met dezelfde staart, anders zou meerwerk zonder opslag
        // en winst in de boeken komen.
        $factor = $direct > 0 ? ($direct + $cumulative) / $direct : 1.0;
        $priced = fn (BudgetOption $option) => (float) $option->quantity * (float) $option->price * $factor;

        $area = (float) ($budget->floor_area ?? 0);

        return new self(
            categories: array_map(fn (float $value) => round($value, 2), $categories),
            chapters: $chapters,
            markups: $markups,
            direct: round($direct, 2),
            markupTotal: round($cumulative, 2),
            totalExcludingVat: $totalExcl,
            vat: $vat,
            vatPercentage: $vatPct,
            totalIncludingVat: round($totalExcl + $vat, 2),
            hours: round($hours, 2),
            lineCount: $lineCount,
            incompleteLines: $incomplete,
            approvedExtras: round($budget->options->where('status', BudgetOption::STATUS_APPROVED)->sum($priced), 2),
            openOptions: round($budget->options->where('status', BudgetOption::STATUS_OPEN)->sum($priced), 2),
            markupFactor: round($factor, 6),
            margin: $totalExcl > 0 ? round(($totalExcl - $direct) / $totalExcl * 100, 2) : 0.0,
            surcharge: $direct > 0 ? round(($totalExcl - $direct) / $direct * 100, 2) : 0.0,
            pricePerSquareMetre: $area > 0 ? round($totalExcl / $area, 2) : null,
        );
    }

    /**
     * De aanneemsom inclusief goedgekeurd meerwerk: wat de klant uiteindelijk
     * betaalt voor het werk zoals het nu is afgesproken.
     */
    public function contractSum(): float
    {
        return round($this->totalExcludingVat + $this->approvedExtras, 2);
    }

    /**
     * Welke opslag hoort bij een gewenste marge, en omgekeerd. De tool rekent
     * beide kanten op omdat mensen in marge denken en in opslag rekenen.
     */
    public static function surchargeForMargin(float $margin): ?float
    {
        return $margin >= 100 ? null : round($margin / (100 - $margin) * 100, 2);
    }

    public static function marginForSurcharge(float $surcharge): float
    {
        return round($surcharge / (100 + $surcharge) * 100, 2);
    }

    /**
     * Het aandeel van elke kostensoort in de directe kosten, voor de staafjes
     * in het overzicht.
     *
     * @return array<string, float>
     */
    public function categoryShares(): array
    {
        if ($this->direct <= 0) {
            return array_map(fn () => 0.0, $this->categories);
        }

        return array_map(fn (float $value) => round($value / $this->direct * 100, 1), $this->categories);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'categories' => $this->categories,
            'chapters' => $this->chapters,
            'markups' => $this->markups,
            'direct' => $this->direct,
            'markup_total' => $this->markupTotal,
            'total_excl_vat' => $this->totalExcludingVat,
            'vat' => $this->vat,
            'total_incl_vat' => $this->totalIncludingVat,
            'hours' => $this->hours,
            'lines' => $this->lineCount,
            'incomplete_lines' => $this->incompleteLines,
            'approved_extras' => $this->approvedExtras,
            'open_options' => $this->openOptions,
            'margin' => $this->margin,
            'surcharge' => $this->surcharge,
            'price_per_m2' => $this->pricePerSquareMetre,
            'contract_sum' => $this->contractSum(),
        ];
    }
}
