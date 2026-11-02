<?php

namespace App\Models;

use App\Enums\ActionSource;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Notifications\TaskAssigned;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'title', 'note', 'customer_id', 'project_id', 'lead_id', 'owner_id',
    'deadline', 'priority', 'status', 'source', 'completed_at',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Melding als iemand anders (of Nova namens iemand) een taak op jouw naam zet. Automations
     * draaien zonder ingelogde gebruiker en melden zich al via Nova in "Algemeen".
     */
    protected static function booted(): void
    {
        static::saved(function (Task $task) {
            $assigner = Auth::user();

            if ($assigner === null || $task->owner_id === null || $task->owner_id === $assigner->id) {
                return;
            }

            if ($task->wasRecentlyCreated || $task->wasChanged('owner_id')) {
                $task->owner?->notify(new TaskAssigned($task, $assigner));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'source' => ActionSource::class,
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where('status', '!=', TaskStatus::Afgerond);
    }

    #[Scope]
    protected function dueToday(Builder $query): Builder
    {
        return $query->whereDate('deadline', '<=', today());
    }

    public function isOverdue(): bool
    {
        return $this->deadline !== null
            && $this->deadline->isBefore(today())
            && $this->status->isOpen();
    }
}
