<?php

namespace App\Models;

use App\Enums\CalculationLineType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'calculation_id', 'position', 'type', 'description', 'quantity', 'unit',
    'unit_price', 'surcharge_pct', 'price_item_id', 'price_source', 'price_edition',
])]
class CalculationLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CalculationLineType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'surcharge_pct' => 'decimal:2',
        ];
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(Calculation::class);
    }

    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(PriceItem::class);
    }

    public function total(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price * (1 + (float) $this->surcharge_pct / 100), 2);
    }
}
