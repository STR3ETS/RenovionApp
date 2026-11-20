<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een raamovereenkomst met een onderaannemer voor doorlopend werk. Loopt zes
 * maanden; een nieuwe periode moet iemand bewust vastleggen.
 */
#[Fillable(['subcontractor_id', 'starts_on', 'ends_on', 'recorded_by'])]
class SubcontractorFramework extends Model
{
    public const MONTHS = 6;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function isRunning(): bool
    {
        return $this->starts_on->lessThanOrEqualTo(today()) && $this->ends_on->greaterThanOrEqualTo(today());
    }

    public function daysLeft(): int
    {
        return (int) today()->diffInDays($this->ends_on, false);
    }
}
