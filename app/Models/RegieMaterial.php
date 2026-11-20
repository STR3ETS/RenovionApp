<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Materiaal dat bij regiewerk apart wordt afgerekend.
 */
#[Fillable(['project_id', 'description', 'quantity', 'unit', 'price', 'invoice_id'])]
class RegieMaterial extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'price' => 'decimal:2'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function total(): float
    {
        return round((float) $this->quantity * (float) $this->price, 2);
    }

    public function isInvoiced(): bool
    {
        return $this->invoice_id !== null;
    }
}
