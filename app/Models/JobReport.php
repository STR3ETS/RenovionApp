<?php

namespace App\Models;

use App\Enums\JobReportType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een melding vanaf de bouwplaats: probleem, meerwerk, materiaaltekort, schade,
 * onveilige situatie of een vraag. Blijft open tot de projectleider reageert en
 * verschijnt tot die tijd in de inbox.
 */
#[Fillable([
    'job_execution_id', 'project_id', 'reported_by', 'type', 'body',
    'urgent', 'status', 'resolved_at', 'resolved_by', 'resolution',
])]
class JobReport extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'afgehandeld';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => JobReportType::class,
            'urgent' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobExecution::class, 'job_execution_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
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
        $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
