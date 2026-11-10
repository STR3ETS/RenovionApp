<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Support\QualityTemplates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * De kwaliteitscontrole en de oplevering van een project: acht stappen, elk met
 * een harde voorwaarde. Je komt niet bij de klantinspectie zolang er restpunten
 * open staan, en niet bij ondertekening zolang de controlepunten niet af zijn.
 */
#[Fillable([
    'project_id', 'template', 'wkb', 'step', 'signed_name', 'signed_at', 'notes',
])]
class QualityReview extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wkb' => 'boolean',
            'signed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(QualityCheck::class)->orderBy('position')->orderBy('id');
    }

    public function snags(): HasMany
    {
        return $this->hasMany(SnagItem::class);
    }

    /**
     * De controle voor een project ophalen, of hem aanmaken met de punten die
     * bij dit soort werk horen.
     */
    public static function forProject(Project $project): self
    {
        $review = self::firstOrCreate(
            ['project_id' => $project->id],
            [
                'template' => QualityTemplates::templateFor($project->type),
                'wkb' => QualityTemplates::fallsUnderWkb($project->type),
            ],
        );

        if ($review->wasRecentlyCreated) {
            $review->seedChecks();
        }

        return $review;
    }

    public function seedChecks(): void
    {
        $position = 0;

        foreach (QualityTemplates::checksFor($this->template) as $title) {
            $this->checks()->create(['title' => $title, 'position' => $position++]);
        }

        if (! $this->wkb) {
            return;
        }

        foreach (QualityTemplates::WKB_CHECKS as $title) {
            $this->checks()->create(['title' => $title, 'wkb' => true, 'position' => $position++]);
        }
    }

    /** De controlepunten die voor dit project gelden. */
    public function applicableChecks(): Collection
    {
        return $this->checks->filter(fn (QualityCheck $check) => $this->wkb || ! $check->wkb);
    }

    public function checksComplete(): bool
    {
        return $this->applicableChecks()->every(fn (QualityCheck $check) => $check->isChecked());
    }

    public function openSnags(): Collection
    {
        return $this->project->snags->where('status', SnagItem::STATUS_OPEN);
    }

    /**
     * Waarom een stap nog niet af kan. Een lege tekst betekent: deze stap mag.
     * Zo staat de reden altijd bij de knop en hoef je niet te zoeken.
     */
    public function blockerFor(int $step): string
    {
        $project = $this->project;

        return match ($step) {
            0 => $this->workIsDone($project),
            1 => $this->checksComplete()
                ? ''
                : 'Niet alle controlepunten zijn afgevinkt.',
            3 => $this->openSnags()->isEmpty()
                ? ''
                : $this->openSnags()->count().' '.($this->openSnags()->count() === 1 ? 'restpunt staat' : 'restpunten staan').' nog open.',
            5 => $this->signed_at !== null
                ? ''
                : 'Het opleverformulier is nog niet getekend.',
            default => '',
        };
    }

    /**
     * Stap één: het werk is klaar. Dat betekent voortgang van minstens 95 procent
     * en geen werkbonnen die nog op goedkeuring wachten, want die kunnen de
     * voortgang en de kosten nog veranderen.
     */
    private function workIsDone(Project $project): string
    {
        $open = WorkOrder::where('project_id', $project->id)
            ->where('status', '!=', WorkOrderStatus::Concept)
            ->whereNotIn('status', [WorkOrderStatus::Goedgekeurd, WorkOrderStatus::Afgesloten])
            ->count();

        if ((int) $project->progress >= 95 && $open === 0) {
            return '';
        }

        $parts = [];

        if ((int) $project->progress < 95) {
            $parts[] = "de voortgang staat op {$project->progress} procent";
        }

        if ($open > 0) {
            $parts[] = $open.' '.($open === 1 ? 'werkbon wacht' : 'werkbonnen wachten').' nog op goedkeuring';
        }

        return ucfirst(implode(' en ', $parts)).'.';
    }

    /** Hoeveel van de acht stappen rond zijn. */
    public function progress(): int
    {
        return (int) round($this->step / count(QualityTemplates::FLOW) * 100);
    }

    public function isDelivered(): bool
    {
        return $this->step >= count(QualityTemplates::FLOW) - 1;
    }
}
