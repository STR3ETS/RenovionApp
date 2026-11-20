<?php

namespace App\Support;

use App\Models\Project;
use App\Models\RegieMaterial;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;

/**
 * De urenstaat van een project doorgerekend.
 *
 * Bij aangenomen werk staan de uren er ter informatie: de prijs is vast. Bij
 * regie rekenen we af: uren maal uurtarief, of dagen maal daggeld, plus
 * materiaal, min wat al gefactureerd is. Een dag is acht uur.
 *
 * Aftekenen legt het aantal uren vast. Komt er daarna een uur bij of gaat er
 * een af, dan klopt de handtekening niet meer en moet er opnieuw afgetekend
 * worden voordat er gefactureerd kan worden.
 */
final class RegieStatement
{
    public const HOURS_PER_DAY = 8;

    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @param  Collection<int, RegieMaterial>  $materials
     */
    private function __construct(
        public readonly Project $project,
        public readonly Collection $entries,
        public readonly Collection $materials,
    ) {}

    public static function for(Project $project): self
    {
        $project->loadMissing(['timeEntries.user', 'regieMaterials']);

        return new self($project, $project->timeEntries, $project->regieMaterials);
    }

    public function isRegie(): bool
    {
        return $this->project->isRegie();
    }

    public function hours(): float
    {
        return round((float) $this->entries->sum('hours'), 2);
    }

    public function openHours(): float
    {
        return round((float) $this->entries->whereNull('invoice_id')->sum('hours'), 2);
    }

    /** Rekenen we per dag af? Dan gaat het daggeld voor het uurtarief. */
    public function perDay(): bool
    {
        return (float) $this->project->regie_day_rate > 0;
    }

    public function rate(): float
    {
        return (float) ($this->perDay() ? $this->project->regie_day_rate : $this->project->regie_hourly_rate);
    }

    /** Uren, of dagen van acht uur als er een daggeld is afgesproken. */
    public function units(float $hours): float
    {
        return $this->perDay() ? round($hours / self::HOURS_PER_DAY, 2) : $hours;
    }

    public function labour(float $hours): float
    {
        return round($this->units($hours) * $this->rate(), 2);
    }

    public function materialsTotal(): float
    {
        return round($this->materials->sum(fn (RegieMaterial $material) => $material->total()), 2);
    }

    public function openMaterials(): float
    {
        return round($this->materials->whereNull('invoice_id')->sum(fn (RegieMaterial $material) => $material->total()), 2);
    }

    /** Alles bij elkaar: arbeid plus materiaal, exclusief btw. */
    public function total(): float
    {
        return round($this->labour($this->hours()) + $this->materialsTotal(), 2);
    }

    /** Wat nog niet op een factuur staat. */
    public function toInvoice(): float
    {
        return round($this->labour($this->openHours()) + $this->openMaterials(), 2);
    }

    public function invoiced(): float
    {
        return round($this->total() - $this->toInvoice(), 2);
    }

    public function isSigned(): bool
    {
        return $this->project->hours_signed_at !== null;
    }

    /** Afgetekend, maar daarna zijn de uren veranderd. */
    public function needsResign(): bool
    {
        return $this->isSigned() && abs((float) $this->project->hours_signed_total - $this->hours()) > 0.001;
    }

    /** Factureren kan pas als de uren zijn afgetekend en sindsdien niet veranderd. */
    public function canInvoice(): bool
    {
        return $this->isRegie() && $this->isSigned() && ! $this->needsResign() && $this->toInvoice() > 0;
    }

    /** Waarom factureren nu niet kan, in gewone taal. */
    public function blocker(): ?string
    {
        return match (true) {
            ! $this->isRegie() => 'Dit is aangenomen werk: de uren staan er ter informatie, de prijs is vast.',
            $this->rate() <= 0 => 'Vul eerst het uurtarief of het daggeld in.',
            ! $this->isSigned() => 'De uren zijn nog niet afgetekend.',
            $this->needsResign() => 'De uren zijn na het aftekenen veranderd. Teken opnieuw af.',
            $this->toInvoice() <= 0 => 'Alles op deze urenstaat is al gefactureerd.',
            default => null,
        };
    }
}
