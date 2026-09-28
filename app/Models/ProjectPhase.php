<?php

namespace App\Models;

use App\Enums\PhaseStatus;
use Database\Factories\ProjectPhaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id', 'position', 'name', 'status', 'responsible_id',
    'planned_start', 'planned_end', 'completed_at',
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
}
