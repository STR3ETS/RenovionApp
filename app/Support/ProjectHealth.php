<?php

namespace App\Support;

use App\Enums\HealthLight;

/**
 * De uitkomst van [ProjectHealthService]: vier stoplichten met toelichting,
 * plus de cijfers die de cockpit en het portfolio tonen (briefing hoofdstuk 7).
 */
final class ProjectHealth
{
    /**
     * @param  array<string, HealthSignal>  $signals  planning, budget, kwaliteit, klant
     */
    public function __construct(
        public readonly array $signals,
        public readonly HealthLight $overall,
        public readonly ?int $expectedProgress,
        public readonly int $progress,
        public readonly ?string $biggestBlocker,
        public readonly ?string $nextMilestone,
        public readonly ?string $nextMilestoneDue,
    ) {}

    public function signal(string $key): HealthSignal
    {
        return $this->signals[$key];
    }

    /**
     * Hoeveel procent het project achterloopt op de planning. Negatief
     * betekent voor op schema; null als er geen planning bekend is.
     */
    public function scheduleVariance(): ?int
    {
        return $this->expectedProgress === null ? null : $this->expectedProgress - $this->progress;
    }

    public function needsAttention(): bool
    {
        return in_array($this->overall, [HealthLight::Oranje, HealthLight::Rood], true);
    }

    /**
     * De lichten die aandacht vragen, slechtste eerst.
     *
     * @return list<HealthSignal>
     */
    public function attentionSignals(): array
    {
        $signals = array_values(array_filter(
            $this->signals,
            fn (HealthSignal $signal) => $signal->light->severity() >= HealthLight::Oranje->severity(),
        ));

        usort($signals, fn (HealthSignal $a, HealthSignal $b) => $b->light->severity() <=> $a->light->severity());

        return $signals;
    }
}
