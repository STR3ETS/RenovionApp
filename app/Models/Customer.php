<?php

namespace App\Models;

use App\Enums\ActionSource;
use App\Enums\TimelineEventType;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

#[Fillable(['name', 'email', 'phone', 'address', 'postal_code', 'city', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(TimelineEvent::class)->latest('happened_at');
    }

    /**
     * Voeg een gebeurtenis toe aan de klanttimeline (het centrale dossier).
     */
    public function recordEvent(
        TimelineEventType $type,
        string $title,
        ?string $body = null,
        ?Model $subject = null,
        ActionSource $source = ActionSource::Handmatig,
        ?array $meta = null,
    ): TimelineEvent {
        return $this->timelineEvents()->create([
            'user_id' => Auth::id(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'type' => $type,
            'source' => $source,
            'title' => $title,
            'body' => $body,
            'happened_at' => now(),
            'meta' => $meta,
        ]);
    }
}
