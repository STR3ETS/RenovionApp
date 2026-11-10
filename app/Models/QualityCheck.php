<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een controlepunt uit de kwaliteitscontrole: iets dat je achteraf niet
 * meer kunt vaststellen, zoals of de waterdichting goed zat.
 */
#[Fillable(['quality_review_id', 'title', 'wkb', 'checked_at', 'checked_by', 'note', 'position'])]
class QualityCheck extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['wkb' => 'boolean', 'checked_at' => 'datetime'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(QualityReview::class, 'quality_review_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function isChecked(): bool
    {
        return $this->checked_at !== null;
    }
}
