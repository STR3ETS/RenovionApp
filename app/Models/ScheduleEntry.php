<?php

namespace App\Models;

use App\Enums\ScheduleEntryType;
use Database\Factories\ScheduleEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'user_id', 'project_id', 'lead_id', 'customer_id', 'type',
    'title', 'date', 'end_date', 'start_time', 'end_time', 'notes', 'done_at',
])]
class ScheduleEntry extends Model
{
    /** @use HasFactory<ScheduleEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ScheduleEntryType::class,
            'date' => 'date',
            'end_date' => 'date',
            'done_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Wie er naast de eigenaar van het blok bij de afspraak is. */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function displayTitle(): string
    {
        return $this->title
            ?? $this->project?->name
            ?? $this->customer?->name
            ?? $this->type->label();
    }
}
