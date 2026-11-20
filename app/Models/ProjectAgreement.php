<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De overeenkomst met een onderaannemer voor één project. Ontstaat bij het
 * gunnen en is pas rond als hij getekend terug is en is vastgelegd.
 */
#[Fillable(['project_id', 'subcontractor_id', 'rfq_id', 'recorded_at', 'recorded_by'])]
class ProjectAgreement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isRecorded(): bool
    {
        return $this->recorded_at !== null;
    }
}
