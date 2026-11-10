<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een stap in de checklist van een klus. Een stap met photo_required kan niet af
 * zonder foto: dat is het bewijsmoment dat je later niet meer kunt maken.
 */
#[Fillable([
    'job_execution_id', 'position', 'title', 'hint', 'photo_required',
    'completed_at', 'completed_by', 'note',
])]
class JobStep extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'photo_required' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobExecution::class, 'job_execution_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->latest();
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    /** De stap wacht nog op het verplichte foto-bewijs. */
    public function needsPhoto(): bool
    {
        return $this->photo_required && $this->photos->isEmpty();
    }
}
