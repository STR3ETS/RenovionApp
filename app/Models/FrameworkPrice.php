<?php

namespace App\Models;

use App\Support\Margin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een vaste verkoopprijs binnen een raamovereenkomst. De marge is wat er
 * overblijft na de kostprijs uit de prijslijst.
 */
#[Fillable(['framework_agreement_id', 'price_item_id', 'description', 'unit', 'fixed_price', 'position'])]
class FrameworkPrice extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fixed_price' => 'decimal:2'];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(FrameworkAgreement::class, 'framework_agreement_id');
    }

    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(PriceItem::class);
    }

    /** Wat het ons kost, volgens de prijslijst. Zonder artikel is dat onbekend. */
    public function costPrice(): ?float
    {
        return $this->priceItem === null ? null : (float) $this->priceItem->unit_price;
    }

    public function marginPct(): ?float
    {
        $cost = $this->costPrice();

        return $cost === null ? null : Margin::pct((float) $this->fixed_price, $cost);
    }
}
