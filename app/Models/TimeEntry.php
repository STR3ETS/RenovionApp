<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een regel op de urenstaat van een project: wie, wanneer, hoeveel uur. Komt
 * vanzelf uit een goedgekeurde werkbon, of iemand voert hem met de hand in.
 */
#[Fillable(['project_id', 'user_id', 'work_order_id', 'date', 'hours', 'description', 'invoice_id', 'created_by'])]
class TimeEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date', 'hours' => 'decimal:2'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isInvoiced(): bool
    {
        return $this->invoice_id !== null;
    }
}
