<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Marketing: waar de aanvragen vandaan komen en wat ze opleveren.
 *
 * De vraag die dit beantwoordt is niet "hoeveel leads kwamen er binnen" maar
 * "welke bron levert werk op". Een bron met twintig aanvragen en één opdracht is
 * minder waard dan een bron met vijf aanvragen en drie opdrachten.
 */
class MarketingInsights
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $period = 'jaar'): array
    {
        $from = match ($period) {
            'kwartaal' => now()->firstOfQuarter(),
            '90' => today()->subDays(90),
            'alles' => null,
            default => now()->startOfYear(),
        };

        $leads = Lead::query()
            ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from))
            ->with('customer')
            ->get();

        return [
            'period' => $period,
            'total' => $leads->count(),
            // Het kanaal waarlangs de aanvraag binnenkwam, en de campagne die
            // eraan hing. Het kanaal is een vast lijstje, de campagne vrije tekst.
            'channels' => $this->breakdown($leads, fn (Lead $lead) => $lead->source?->label() ?? 'niet vastgelegd'),
            'sources' => $this->breakdown($leads, fn (Lead $lead) => $lead->campaign ?: 'geen campagne'),
            'services' => $this->breakdown($leads, fn (Lead $lead) => $lead->service ?: 'niet opgegeven'),
            'per_month' => $this->perMonth($leads),
            'won_value' => round((float) $leads->where('status', LeadStatus::Project)->sum('value'), 2),
            'pipeline_value' => round((float) $leads->filter(fn (Lead $l) => $l->status->isOpen())->sum('value'), 2),
            // De beste bron vraagt minstens drie aanvragen: één toevallige
            // klapper zegt niets over een kanaal.
            'best_source' => $this->breakdown($leads, fn (Lead $lead) => $lead->campaign ?: 'geen campagne')
                ->filter(fn (array $row) => $row['total'] >= 3 && $row['label'] !== 'geen campagne')
                ->sortByDesc('conversion')
                ->first(),
            'repeat_customers' => Project::selectRaw('customer_id, count(*) as total')
                ->groupBy('customer_id')
                ->havingRaw('count(*) > 1')
                ->count(),
        ];
    }

    /**
     * Per waarde van een kenmerk: hoeveel aanvragen, hoeveel opdrachten, welke
     * conversie en wat de gemiddelde opdracht waard is.
     *
     * @param  Collection<int, Lead>  $leads
     * @return Collection<string, array{label: string, total: int, won: int, lost: int, open: int, conversion: float, value: float, average: float}>
     */
    private function breakdown(Collection $leads, callable $key): Collection
    {
        return $leads->groupBy($key)->map(function (Collection $set, string $label) {
            $won = $set->where('status', LeadStatus::Project);
            $lost = $set->where('status', LeadStatus::Verloren);
            $decided = $won->count() + $lost->count();

            return [
                'label' => $label,
                'total' => $set->count(),
                'won' => $won->count(),
                'lost' => $lost->count(),
                'open' => $set->filter(fn (Lead $l) => $l->status->isOpen())->count(),
                // Conversie over wat beslist is, niet over alles: open aanvragen
                // kunnen nog beide kanten op.
                'conversion' => $decided > 0 ? round($won->count() / $decided * 100, 1) : 0.0,
                'value' => round((float) $won->sum('value'), 2),
                'average' => $won->count() > 0 ? round((float) $won->sum('value') / $won->count(), 2) : 0.0,
            ];
        })->sortByDesc('total');
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return array<string, int>
     */
    private function perMonth(Collection $leads): array
    {
        $months = collect(range(-11, 0))
            ->map(fn (int $i) => now()->startOfMonth()->addMonths($i)->format('Y-m'));

        return $months->mapWithKeys(fn (string $month) => [
            $month => $leads->filter(fn (Lead $l) => $l->created_at->format('Y-m') === $month)->count(),
        ])->all();
    }
}
