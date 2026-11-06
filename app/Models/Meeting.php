<?php

namespace App\Models;

use App\Enums\MeetingKind;
use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gespreksverslag (Nova notuleert): opname van een overleg, het transcript en het verslag dat Nova
 * daarvan maakt. Het verslag is een concept: acties worden pas taken als de gebruiker ze aanvinkt.
 */
#[Fillable([
    'title', 'kind', 'project_id', 'user_id', 'status', 'consent_at', 'started_at', 'ended_at', 'duration_seconds',
    'audio_path', 'audio_mime', 'audio_bytes', 'audio_chunks', 'audio_deleted_at',
    'provider', 'provider_id', 'transcript', 'speakers', 'summary', 'decisions', 'actions', 'questions', 'error', 'attempts',
])]
class Meeting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MeetingKind::class,
            'status' => MeetingStatus::class,
            'consent_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'audio_deleted_at' => 'datetime',
            'transcript' => 'array',
            'speakers' => 'array',
            'decisions' => 'array',
            'actions' => 'array',
            'questions' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Wie het mag zien: kantoor alles, een uitvoerder zijn eigen opnames en die van zijn projecten.
     */
    public function isAccessibleBy(User $user): bool
    {
        return $user->can('manage-crm')
            || $this->user_id === $user->id
            || ($this->project !== null && $this->project->isAccessibleBy($user));
    }

    /** @param  Builder<self>  $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->when($user->cannot('manage-crm'), fn (Builder $inner) => $inner->where(fn (Builder $scope) => $scope
            ->where('user_id', $user->id)
            ->orWhereHas('project.craftsmen', fn ($craftsmen) => $craftsmen->where('users.id', $user->id))));
    }

    public function hasAudio(): bool
    {
        return $this->audio_path !== null && $this->audio_deleted_at === null && $this->audio_bytes > 0;
    }

    /** Naam van een spreker: wat Nova of de gebruiker herkende, anders "Spreker A". */
    public function speakerName(string $label): string
    {
        return $this->speakers[$label] ?? 'Spreker '.$label;
    }
}
