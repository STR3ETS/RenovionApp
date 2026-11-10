<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een regel op een inkooporder: wat er besteld is en voor welke prijs.
 */
#[Fillable(['purchase_order_id', 'price_item_id', 'description', 'quantity', 'unit', 'unit_price', 'position'])]
class PurchaseOrderLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(PriceItem::class);
    }

    public function total(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }
}
