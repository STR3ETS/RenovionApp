<?php

namespace App\Models;

use App\Enums\PhaseStatus;
use Database\Factories\ProjectPhaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id', 'position', 'name', 'status', 'responsible_id',
    'planned_start', 'planned_end', 'completed_at', 'approved_at', 'approved_by', 'gate_note',
])]
class ProjectPhase extends Model
{
    /** @use HasFactory<ProjectPhaseFactory> */
    use HasFactory;

    /**
     * De vaste fasen 0–8 uit briefing v2 §7. Elk nieuw project krijgt ze allemaal.
     *
     * @var list<string>
     */
    public const NAMES = [
        'Opdracht & overdracht',
        'Voortraject',
        'Werkvoorbereiding',
        'Inkoop & planning',
        'Uitvoering',
        'Controle & klantacties',
        'Vooroplevering',
        'Oplevering',
        'Nazorg',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PhaseStatus::class,
            'planned_start' => 'date',
            'planned_end' => 'date',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class)->orderBy('position')->orderBy('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'project_phase_id')->latest();
    }

    /**
     * Voortgang van deze fase (0–1): gereed telt volledig, anders het
     * aandeel afgeronde werkpakketten.
     */
    public function completionFraction(): float
    {
        if ($this->status === PhaseStatus::Gereed) {
            return 1.0;
        }

        if ($this->workPackages->isEmpty()) {
            return 0.0;
        }

        return $this->workPackages->filter(fn (WorkPackage $package) => $package->isDone())->count()
            / $this->workPackages->count();
    }
}
