<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een bieding op een prijsaanvraag. Een bieding die niet alle posten
 * prijst, is niet vergelijkbaar: daarom staat de compleetheid erbij.
 */
#[Fillable(['rfq_id', 'subcontractor_id', 'price', 'can_start_on', 'days', 'conditions', 'completeness', 'notes', 'received_on'])]
class RfqBid extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'can_start_on' => 'date', 'received_on' => 'date'];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
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
