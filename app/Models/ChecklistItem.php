<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['work_package_id', 'label', 'requires_photos', 'done_at', 'done_by', 'position'])]
class ChecklistItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'done_at' => 'datetime',
            'requires_photos' => 'integer',
        ];
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    /**
     * Vereist dit item foto-bewijs? Handhaving volgt in de foto-bewijs-sprint (§8).
     */
    public function requiresPhotos(): bool
    {
        return $this->requires_photos > 0;
    }
}
