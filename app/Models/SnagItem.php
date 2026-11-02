<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een restpunt: het verschil tussen een oplevering die klaar is en een die
 * "bijna" klaar is. Een restpunt heeft altijd een locatie, want "kras in de
 * wand" zonder plek is onvindbaar voor wie het moet herstellen.
 */
#[Fillable([
    'project_id', 'quality_review_id', 'title', 'location', 'description', 'status',
    'assigned_to', 'subcontractor_id', 'due_on', 'resolved_at', 'resolved_by', 'source',
])]
class SnagItem extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'bezig';

    public const STATUS_RESOLVED = 'hersteld';

    public const STATUS_ACCEPTED = 'geaccepteerd';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_OPEN => 'Open',
        self::STATUS_IN_PROGRESS => 'Bezig',
        self::STATUS_RESOLVED => 'Hersteld',
        self::STATUS_ACCEPTED => 'Geaccepteerd door de klant',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(QualityReview::class, 'quality_review_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->latest();
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_RESOLVED => 'bg-lead-500/15 text-lead-300',
            self::STATUS_ACCEPTED => 'bg-green-500/15 text-green-300',
            self::STATUS_IN_PROGRESS => 'bg-amber-500/15 text-amber-300',
            default => 'bg-red-500/15 text-red-300',
        };
    }

    /** Wie het oplost: een eigen mens, of de partij die het werk deed. */
    public function ownerName(): string
    {
        return $this->assignee?->name
            ?? $this->subcontractor?->name
            ?? 'nog niet toegewezen';
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_on !== null && $this->due_on->isPast();
    }
}
