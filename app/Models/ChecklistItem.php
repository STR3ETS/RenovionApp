<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->latest();
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
     * Vereist dit item foto-bewijs?
     */
    public function requiresPhotos(): bool
    {
        return $this->requires_photos > 0;
    }

    /**
     * Is het minimum aantal bewijsfoto's aanwezig (briefing §9)?
     */
    public function hasRequiredPhotos(): bool
    {
        return ! $this->requiresPhotos()
            || $this->photos()->count() >= $this->requires_photos;
    }
}
