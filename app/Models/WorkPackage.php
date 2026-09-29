<?php

namespace App\Models;

use App\Enums\PhaseStatus;
use Database\Factories\WorkPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id', 'project_phase_id', 'name', 'description', 'status',
    'responsible_id', 'deadline', 'completed_at', 'position',
])]
class WorkPackage extends Model
{
    /** @use HasFactory<WorkPackageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PhaseStatus::class,
            'deadline' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'project_phase_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    public function isDone(): bool
    {
        return $this->status === PhaseStatus::Gereed;
    }

    public function isOverdue(): bool
    {
        return $this->deadline !== null
            && $this->deadline->isPast()
            && ! $this->isDone();
    }

    /**
     * Alle checklistitems afgevinkt? (Een werkpakket zonder checklist telt als compleet.)
     */
    public function checklistComplete(): bool
    {
        return $this->items->every(fn (ChecklistItem $item) => $item->isDone());
    }
}
