<?php

namespace App\Models;

use App\Support\Margin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een bieding op een prijsaanvraag. Een bieding die niet alle posten
 * prijst, is niet vergelijkbaar: daarom staat de compleetheid erbij.
 */
#[Fillable([
    'rfq_id', 'subcontractor_id', 'price', 'sale_quantity', 'sale_unit', 'sale_price',
    'can_start_on', 'days', 'conditions', 'completeness', 'notes', 'received_on',
    'via_whatsapp', 'margin_approval', 'purchase_order_id',
])]
class RfqBid extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_quantity' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'can_start_on' => 'date',
            'received_on' => 'date',
            'via_whatsapp' => 'boolean',
            'margin_approval' => 'array',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Waarvoor we dit werk zelf verkopen: hoeveelheid maal prijs. Zonder
     * hoeveelheid is de prijs het hele bedrag.
     */
    public function saleTotal(): ?float
    {
        if ($this->sale_price === null) {
            return null;
        }

        return round((float) ($this->sale_quantity ?? 1) * (float) $this->sale_price, 2);
    }

    /** Wat er van de verkoop overblijft als we dit bod aannemen. */
    public function marginPct(): ?float
    {
        $sale = $this->saleTotal();

        return $sale === null ? null : Margin::pct($sale, (float) $this->price);
    }

    /** Onder de bodem: gunnen kan alleen met een reden en akkoord van de directie. */
    public function needsDirectorApproval(): bool
    {
        $margin = $this->marginPct();

        return $margin !== null && $margin < Margin::floor();
    }

    /** Het verschil met wat ervoor begroot staat, in procenten. */
    public function varianceAgainstBudget(): ?float
    {
        $budget = (float) $this->rfq->budget;

        return $budget > 0 ? round(((float) $this->price - $budget) / $budget * 100, 1) : null;
    }

    public function isComplete(): bool
    {
        return $this->completeness >= 95;
    }
}
