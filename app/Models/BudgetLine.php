<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een begrotingsregel. De formule is:
 *
 *     q * (n * tarief + m + e + o) + v
 *
 * hoeveelheid maal de som van normuren tegen tarief, materiaal, materieel en
 * onderaanneming per eenheid, plus overige kosten. Een regel kan een eigen
 * tarief hebben; staat dat leeg, dan geldt het tarief van de begroting.
 *
 * Een tekstregel rekent niet mee: die staat er alleen voor de leesbaarheid.
 */
#[Fillable([
    'budget_paragraph_id', 'type', 'description', 'quantity', 'unit',
    'norm_hours', 'material', 'equipment', 'subcontracting', 'other',
    'hourly_rate', 'source', 'price_item_id', 'position',
])]
class BudgetLine extends Model
{
    public const TYPE_LINE = 'regel';

    public const TYPE_TEXT = 'tekst';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'norm_hours' => 'decimal:4',
            'material' => 'decimal:2',
            'equipment' => 'decimal:2',
            'subcontracting' => 'decimal:2',
            'other' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function paragraph(): BelongsTo
    {
        return $this->belongsTo(BudgetParagraph::class, 'budget_paragraph_id');
    }

    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(PriceItem::class);
    }

    public function countsTowardsTotal(): bool
    {
        return $this->type !== self::TYPE_TEXT;
    }

    /**
     * De kosten van deze regel, uitgesplitst per soort zodat het overzicht kan
     * laten zien waar het geld zit.
     *
     * @return array{uren: float, arbeid: float, materiaal: float, materieel: float, onderaanneming: float, overig: float, totaal: float}
     */
    public function costs(float $budgetRate): array
    {
        $rate = $this->hourly_rate !== null ? (float) $this->hourly_rate : $budgetRate;
        $quantity = (float) $this->quantity;
        $hours = $quantity * (float) $this->norm_hours;

        $costs = [
            'uren' => $hours,
            'arbeid' => $hours * $rate,
            'materiaal' => $quantity * (float) $this->material,
            'materieel' => $quantity * (float) $this->equipment,
            'onderaanneming' => $quantity * (float) $this->subcontracting,
            'overig' => $quantity * (float) $this->other,
        ];

        $costs['totaal'] = $costs['arbeid'] + $costs['materiaal'] + $costs['materieel']
            + $costs['onderaanneming'] + $costs['overig'];

        return $costs;
    }

    /**
     * Een regel met een hoeveelheid maar zonder enige prijs of norm: daar is de
     * calculator nog niet klaar. Die regels worden geteld, zodat je niet met een
     * half ingevulde begroting naar de klant gaat.
     */
    public function isIncomplete(): bool
    {
        if (! $this->countsTowardsTotal() || (float) $this->quantity <= 0) {
            return false;
        }

        return (float) $this->norm_hours === 0.0
            && (float) $this->material === 0.0
            && (float) $this->equipment === 0.0
            && (float) $this->subcontracting === 0.0
            && (float) $this->other === 0.0;
    }

    /**
     * Nederlandse getalnotatie ontleden: "1.234,56" en "1234.56" leveren beide
     * hetzelfde op. De calculator typt met komma's.
     */
    public static function parseNumber(mixed $value): float
    {
        $text = trim(str_replace([' ', 'EUR', '%', "\u{20AC}"], '', (string) $value));

        if ($text === '') {
            return 0.0;
        }

        if (str_contains($text, ',')) {
            $text = str_replace(',', '.', str_replace('.', '', $text));
        }

        return is_numeric($text) ? (float) $text : 0.0;
    }
}
