<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een werkbon: wat er op een dag is gedaan, door wie, in hoeveel uur en met welk
 * materiaal. De uitvoerder dient hem in, de projectleider keurt hem goed.
 */
#[Fillable([
    'number', 'project_id', 'user_id', 'schedule_entry_id', 'job_execution_id',
    'work_package_id', 'task_id', 'title', 'date', 'end_date', 'status',
    'hours', 'budget_hours', 'hourly_rate', 'materials', 'checklist', 'notes',
    'signed_name', 'signed_at', 'signature_path', 'submitted_at',
    'approved_by', 'approved_at', 'rejection_reason',
])]
class WorkOrder extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'date' => 'date',
            'end_date' => 'date',
            'hours' => 'decimal:2',
            'budget_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'materials' => 'array',
            'checklist' => 'array',
            'signed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function scheduleEntry(): BelongsTo
    {
        return $this->belongsTo(ScheduleEntry::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobExecution::class, 'job_execution_id');
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    #[Scope]
    protected function awaitingApproval(Builder $query): void
    {
        $query->where('status', WorkOrderStatus::Controle);
    }

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->whereIn('status', [WorkOrderStatus::Goedgekeurd, WorkOrderStatus::Afgesloten]);
    }

    /**
     * Doorlopende nummering per jaar: WB-2026-0001.
     */
    public static function nextNumber(): string
    {
        $year = now()->year;
        $last = self::where('number', 'like', "WB-{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 0 : (int) str($last)->afterLast('-')->value();

        return sprintf('WB-%d-%04d', $year, $sequence + 1);
    }

    /** De kosten van deze bon: geboekte uren tegen het vastgelegde tarief. */
    public function laborCost(): float
    {
        return (float) $this->hours * (float) ($this->hourly_rate ?? 0);
    }

    /** Afwijking tegenover de begrote uren, in uren. */
    public function hoursVariance(): ?float
    {
        if ((float) $this->budget_hours === 0.0) {
            return null;
        }

        return (float) $this->hours - (float) $this->budget_hours;
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($user->id === $this->user_id) {
            return true;
        }

        return $user->can('manage-crm') && $this->project->isAccessibleBy($user);
    }
}
