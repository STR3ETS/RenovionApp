<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Support\JobChecklistTemplates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

/**
 * Een klus: de uitvoering van een planningsblok op de bouwplaats. Het blok zegt
 * wie, wat, waar en wanneer; de klus houdt bij hoe het werk verloopt.
 */
#[Fillable([
    'schedule_entry_id', 'project_id', 'user_id', 'status', 'lmra_confirmed_at',
    'started_at', 'finished_at', 'paused_minutes', 'paused_at', 'hours', 'notes',
])]
class JobExecution extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'lmra_confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'paused_at' => 'datetime',
            'hours' => 'decimal:2',
        ];
    }

    public function scheduleEntry(): BelongsTo
    {
        return $this->belongsTo(ScheduleEntry::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(JobStep::class)->orderBy('position')->orderBy('id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(JobReport::class)->latest();
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'id', 'job_execution_id');
    }

    /** Alle foto's van deze klus, via de stappen. */
    public function stepPhotos(): HasManyThrough
    {
        return $this->hasManyThrough(Photo::class, JobStep::class, 'job_execution_id', 'job_step_id');
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', '!=', JobStatus::Klaar);
    }

    /**
     * Haal de klus van een planningsblok op, of maak hem aan met de checklist die
     * bij het werk past. Een uitvoerder die zijn blok opent, ziet dus meteen de
     * stappen van zijn vak.
     */
    public static function forEntry(ScheduleEntry $entry): self
    {
        $job = self::firstOrCreate(
            ['schedule_entry_id' => $entry->id],
            [
                'project_id' => $entry->project_id,
                'user_id' => $entry->user_id,
                'status' => JobStatus::Gepland,
            ],
        );

        if ($job->wasRecentlyCreated) {
            $job->seedSteps($entry);
        }

        return $job;
    }

    /** Zet de checklist van het bijpassende vak klaar. */
    public function seedSteps(?ScheduleEntry $entry = null): void
    {
        $entry ??= $this->scheduleEntry;
        $template = JobChecklistTemplates::for(
            $entry?->displayTitle() ?? '',
            $this->project?->type,
        );

        foreach ($template as $position => $step) {
            $this->steps()->create([...$step, 'position' => $position]);
        }
    }

    public function completedSteps(): int
    {
        return $this->steps->whereNotNull('completed_at')->count();
    }

    public function progress(): int
    {
        $total = $this->steps->count();

        return $total === 0 ? 0 : (int) round($this->completedSteps() / $total * 100);
    }

    /** Stappen die nog een verplichte foto missen: hierop blokkeert het afronden. */
    public function stepsMissingEvidence(): Collection
    {
        return $this->steps->filter(
            fn (JobStep $step) => $step->photo_required && $step->photos->isEmpty(),
        );
    }

    public function openReports(): Collection
    {
        return $this->reports->where('status', 'open');
    }

    /** Mag deze gebruiker deze klus uitvoeren of bekijken? */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->id === $this->user_id) {
            return true;
        }

        return $user->can('manage-crm') && (
            $this->project === null || $this->project->isAccessibleBy($user)
        );
    }

    /**
     * "Dag 2 van 5": waar je in een meerdaagse klus zit. Weekenddagen tellen niet.
     */
    public function dayLabel(): ?string
    {
        $entry = $this->scheduleEntry;

        if ($entry === null || $entry->date->isFuture()) {
            return null;
        }

        $end = $entry->end_date ?? $entry->date;
        $total = $entry->date->diffInWeekdays($end) + 1;
        $cap = today()->greaterThan($end) ? $end : today();
        $current = $entry->date->diffInWeekdays($cap) + 1;

        return $total > 1 ? "dag {$current} van {$total}" : null;
    }

    /**
     * Een schatting van de gewerkte uren voor het afrondformulier: de tijd tussen
     * starten en nu, pauzes eraf, afgerond op een half uur. Zonder starttijd gaan
     * we uit van een werkdag van acht uur.
     */
    public function estimatedHours(): float
    {
        if ($this->started_at === null) {
            return 8.0;
        }

        $minutes = $this->started_at->diffInMinutes(now()) - $this->paused_minutes;
        $hours = round(max(0, $minutes) / 60 * 2) / 2;

        return min(12.0, max(0.5, $hours));
    }
}
