<?php

namespace App\Models;

use App\Enums\CalculationStatus;
use Database\Factories\CalculationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id', 'lead_id', 'title', 'description', 'status',
    'risk_pct', 'margin_pct', 'vat_pct', 'created_by',
])]
class Calculation extends Model
{
    /** @use HasFactory<CalculationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CalculationStatus::class,
            'risk_pct' => 'decimal:2',
            'margin_pct' => 'decimal:2',
            'vat_pct' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CalculationLine::class)->orderBy('position')->orderBy('id');
    }

    public function isLocked(): bool
    {
        return $this->status === CalculationStatus::Definitief;
    }

    /**
     * Voeg een regel toe met snapshot van de gebruikte prijsbron, zodat de
     * calculatie later exact kan aantonen met welke prijsset hij is gemaakt.
     *
     * @param  array<string, mixed>  $data
     */
    public function addLine(array $data): CalculationLine
    {
        $priceItem = isset($data['price_item_id']) ? PriceItem::find($data['price_item_id']) : null;

        return $this->lines()->create([
            'position' => ((int) $this->lines()->max('position')) + 1,
            'type' => $data['type'],
            'description' => $data['description'],
            'quantity' => $data['quantity'] ?? 1,
            'unit' => $data['unit'] ?? $priceItem?->unit,
            'unit_price' => $data['unit_price'] ?? $priceItem?->effectivePrice() ?? 0,
            'surcharge_pct' => $data['surcharge_pct'] ?? $priceItem?->surcharge_pct ?? 0,
            'price_item_id' => $priceItem?->id,
            'price_source' => $priceItem?->source,
            'price_edition' => $priceItem?->edition,
        ]);
    }

    public function subtotal(): float
    {
        return round($this->lines->sum(fn (CalculationLine $line) => $line->total()), 2);
    }

    public function riskAmount(): float
    {
        return round($this->subtotal() * (float) $this->risk_pct / 100, 2);
    }

    public function marginAmount(): float
    {
        return round(($this->subtotal() + $this->riskAmount()) * (float) $this->margin_pct / 100, 2);
    }

    public function totalExcl(): float
    {
        return round($this->subtotal() + $this->riskAmount() + $this->marginAmount(), 2);
    }

    public function vatAmount(): float
    {
        return round($this->totalExcl() * (float) $this->vat_pct / 100, 2);
    }

    public function totalIncl(): float
    {
        return round($this->totalExcl() + $this->vatAmount(), 2);
    }
}
