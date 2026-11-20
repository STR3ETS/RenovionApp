<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Het offertedashboard: wat is verstuurd, wat is geopend, wat is getekend en wat
 * gaat er nog gebeuren.
 *
 * De scoringskans komt uit gedrag, niet uit een onderbuikgevoel. Wie vragen
 * stelt over zijn offerte, overweegt serieus; wie twee keer kijkt, denkt erover
 * na; wie hem nooit opent, doet er niets mee. Dat levert vier niveaus op: zestig,
 * vijfenveertig, vijfendertig en vijftien procent.
 */
class QuoteDashboard
{
    /** De kans dat een offerte getekend wordt, op basis van wat de klant doet. */
    public const CHANCE_QUESTIONS = 0.60;

    public const CHANCE_REPEAT_VIEW = 0.45;

    public const CHANCE_OPENED = 0.35;

    public const CHANCE_UNOPENED = 0.15;

    /** Na zoveel dagen zonder beslissing beschouwen we een offerte als verlopen. */
    public const EXPIRES_AFTER_DAYS = 30;

    /** @var array<string, string> */
    public const PERIODS = [
        'jaar' => 'Dit jaar',
        'kwartaal' => 'Dit kwartaal',
        '90' => 'Laatste 90 dagen',
        'alles' => 'Alles',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(string $period = 'jaar', ?int $sellerId = null, ?string $type = null): array
    {
        $from = $this->from($period);

        $quotes = Quote::query()
            ->whereNotNull('sent_at')
            ->when($from !== null, fn ($q) => $q->where('sent_at', '>=', $from))
            ->with(['customer', 'lead.assignee', 'project', 'views', 'questions'])
            ->withCount(['views', 'questions' => fn ($q) => $q->whereNull('answered_at')])
            ->get()
            ->when($sellerId !== null, fn (Collection $c) => $c->filter(
                fn (Quote $quote) => $quote->lead?->assigned_to === $sellerId,
            ));

        // De soorten werk die in deze periode voorkomen, voor het filter.
        $types = $quotes->map(fn (Quote $quote) => $this->type($quote))->unique()->sort()->values();

        if ($type !== null) {
            $quotes = $quotes->filter(fn (Quote $quote) => $this->type($quote) === $type);
        }

        $rows = $quotes->map(fn (Quote $quote) => [
            'quote' => $quote,
            'state' => $this->state($quote),
            'chance' => $this->chance($quote),
            'days_to_open' => $quote->views->isNotEmpty()
                ? (int) $quote->sent_at->diffInDays($quote->views->min('viewed_at'))
                : null,
            'days_to_sign' => $quote->accepted_at !== null
                ? (int) $quote->sent_at->diffInDays($quote->accepted_at)
                : null,
        ])->sortByDesc(fn (array $row) => $row['quote']->sent_at)->values();

        $won = $rows->where('state', 'getekend');
        $lost = $rows->where('state', 'afgewezen');
        $expired = $rows->where('state', 'verlopen');
        $open = $rows->where('state', 'open');
        $decided = $won->count() + $lost->count() + $expired->count();

        $value = fn (Collection $set) => round($set->sum(fn (array $row) => (float) $row['quote']->total), 2);

        $opened = $rows->filter(fn (array $row) => $row['quote']->views_count > 0);

        return [
            'rows' => $rows,
            'period' => $period,
            'seller' => $sellerId,
            'type' => $type,
            'types' => $types,
            'opened' => $opened,
            'opened_value' => $value($opened),
            'months' => $this->months($rows),
            'by_type' => $this->groups($rows, fn (array $row) => $this->type($row['quote'])),
            'by_seller' => $this->groups($rows, fn (array $row) => $row['quote']->lead?->assignee?->name ?? 'Niet toegewezen'),
            'count' => $rows->count(),
            'value' => $value($rows),
            'won' => $won,
            'won_value' => $value($won),
            'lost' => $lost,
            'lost_value' => $value($lost),
            'expired' => $expired,
            'open' => $open,
            'open_value' => $value($open),
            // De gewogen waarde van wat nog open staat.
            'weighted' => round($open->sum(fn (array $row) => (float) $row['quote']->total * $row['chance']), 2),
            // Hitrate op aantal en op geld: die lopen vaak uiteen.
            'hit_rate' => $decided > 0 ? round($won->count() / $decided * 100, 1) : 0.0,
            'hit_rate_value' => ($value($won) + $value($lost) + $value($expired)) > 0
                ? round($value($won) / ($value($won) + $value($lost) + $value($expired)) * 100, 1)
                : 0.0,
            'open_rate' => $rows->count() > 0
                ? round($rows->filter(fn (array $row) => $row['quote']->views_count > 0)->count() / $rows->count() * 100, 1)
                : 0.0,
            'average_value' => $rows->count() > 0 ? round($value($rows) / $rows->count(), 2) : 0.0,
            'days_to_open' => $this->average($rows->pluck('days_to_open')->filter()),
            'days_to_sign' => $this->average($rows->pluck('days_to_sign')->filter()),
            'lost_reasons' => $this->lostReasons($lost),
        ];
    }

    /**
     * Waar een offerte staat. Verlopen is geen status in de database maar een
     * conclusie: dertig dagen uit en nog niets gehoord.
     */
    public function state(Quote $quote): string
    {
        return match (true) {
            $quote->status === QuoteStatus::Akkoord => 'getekend',
            $quote->status === QuoteStatus::Afgewezen => 'afgewezen',
            $quote->sent_at->diffInDays(now()) > self::EXPIRES_AFTER_DAYS => 'verlopen',
            default => 'open',
        };
    }

    /** De scoringskans uit het gedrag van de klant. */
    public function chance(Quote $quote): float
    {
        return match (true) {
            $quote->questions_count > 0 => self::CHANCE_QUESTIONS,
            $quote->views_count >= 2 => self::CHANCE_REPEAT_VIEW,
            $quote->views_count >= 1 => self::CHANCE_OPENED,
            default => self::CHANCE_UNOPENED,
        };
    }

    /** Het soort werk van een offerte: uit de aanvraag, anders uit het project. */
    public function type(Quote $quote): string
    {
        return $quote->lead?->service ?: ($quote->project?->type ?: 'Overig');
    }

    /**
     * Verzonden en getekende waarde per maand van dit jaar, tot en met deze maand.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, sent: float, won: float}>
     */
    private function months(Collection $rows): array
    {
        $out = [];

        foreach (range(1, (int) now()->month) as $month) {
            $inMonth = $rows->filter(fn (array $row) => $row['quote']->sent_at->isCurrentYear()
                && (int) $row['quote']->sent_at->month === $month);

            $out[] = [
                'label' => rtrim(Carbon::create(null, $month, 1)->translatedFormat('M'), '.'),
                'sent' => round($inMonth->sum(fn (array $row) => (float) $row['quote']->total), 2),
                'won' => round($inMonth->where('state', 'getekend')->sum(fn (array $row) => (float) $row['quote']->total), 2),
            ];
        }

        return $out;
    }

    /**
     * Dezelfde cijfers per groep (soort werk, verkoper), grootste waarde eerst.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function groups(Collection $rows, callable $key): Collection
    {
        return $rows->groupBy($key)->map(function (Collection $set, string $name) {
            $won = $set->where('state', 'getekend');
            $decided = $set->whereIn('state', ['getekend', 'afgewezen', 'verlopen'])->count();

            return [
                'name' => $name,
                'count' => $set->count(),
                'value' => round($set->sum(fn (array $row) => (float) $row['quote']->total), 2),
                'won' => $won->count(),
                'won_value' => round($won->sum(fn (array $row) => (float) $row['quote']->total), 2),
                'hit_rate' => $decided > 0 ? round($won->count() / $decided * 100, 1) : 0.0,
                'days_to_sign' => $this->average($won->pluck('days_to_sign')->filter()),
            ];
        })->sortByDesc('value')->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lost
     * @return Collection<string, int>
     */
    private function lostReasons(Collection $lost): Collection
    {
        return $lost
            ->map(fn (array $row) => $row['quote']->lead?->lost_reason ?: 'Geen reden vastgelegd')
            ->countBy()
            ->sortDesc();
    }

    /**
     * @param  Collection<int, int>  $values
     */
    private function average(Collection $values): ?float
    {
        return $values->isEmpty() ? null : round($values->avg(), 1);
    }

    private function from(string $period): ?Carbon
    {
        return match ($period) {
            'kwartaal' => now()->firstOfQuarter(),
            '90' => today()->subDays(90),
            'alles' => null,
            default => now()->startOfYear(),
        };
    }
}
