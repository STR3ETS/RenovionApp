<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * Nacalculatie: begroot tegenover werkelijk en verwacht, per kostensoort.
 *
 * Werkelijk komt uit goedgekeurde werkbonnen (arbeid) en goedgekeurde
 * inkoopfacturen (materiaal, materieel, onderaanneming). Verwacht is het eerlijke
 * eindbeeld: het werkelijke bedrag plus wat er volgens de begroting nog komt,
 * gecorrigeerd voor hoe het tot nu toe loopt.
 *
 * Die correctie is bewust voorzichtig. Vroeg in een project zijn er te weinig
 * uren gemeten om op basis daarvan het hele project te herrekenen, dus weegt de
 * gemeten efficiëntie mee naar rato van hoeveel uren er al geboekt zijn. Pas als
 * een derde van de begrote uren geboekt is, telt de meting volledig.
 */
class CostReviewService
{
    /** Vanaf dit deel van de begrote uren weegt de gemeten efficiëntie volledig mee. */
    private const FULL_WEIGHT_AT = 0.3;

    /**
     * @return array<string, mixed>|null Null als er geen definitieve begroting is om tegen af te zetten.
     */
    public function for(Project $project): ?array
    {
        $budget = $this->budgetFor($project);

        if ($budget === null) {
            return null;
        }

        $totals = $budget->totals();
        $progress = min(1.0, (int) $project->progress / 100);

        $labour = $this->labour($project, $budget);
        $purchases = $this->purchases($project);
        $commitments = $this->openCommitments($project);

        // Hoe de uren lopen tegenover wat ervoor stond, voorzichtig gewogen.
        $efficiency = $this->efficiency($labour, $totals->hours);

        $rows = [
            [
                'name' => 'Arbeid',
                'budgeted' => $totals->categories['arbeid'],
                'actual' => $labour['cost'],
                'expected' => max(
                    $labour['cost'],
                    $labour['cost'] + $totals->categories['arbeid'] * (1 - $progress) * $efficiency,
                ),
                'note' => sprintf(
                    '%s van %s begrote uren geboekt',
                    $this->hours($labour['hours']),
                    $this->hours($totals->hours),
                ),
            ],
            [
                'name' => 'Materiaal',
                'budgeted' => $totals->categories['materiaal'],
                'actual' => $purchases['materiaal'],
                'expected' => $progress >= 1
                    ? $purchases['materiaal']
                    : max($totals->categories['materiaal'], $purchases['materiaal'] + $commitments['materiaal']),
                'note' => $this->purchaseNote($purchases['count_materiaal'], $commitments['materiaal']),
            ],
            [
                'name' => 'Materieel',
                'budgeted' => $totals->categories['materieel'],
                'actual' => $purchases['materieel'],
                'expected' => $progress >= 1
                    ? $purchases['materieel']
                    : max($totals->categories['materieel'], $purchases['materieel'] + $commitments['materieel']),
                'note' => $this->purchaseNote($purchases['count_materieel'], $commitments['materieel']),
            ],
            [
                'name' => 'Onderaanneming',
                'budgeted' => $totals->categories['onderaanneming'],
                'actual' => $purchases['onderaanneming'],
                'expected' => $progress >= 1
                    ? $purchases['onderaanneming']
                    : max($totals->categories['onderaanneming'], $purchases['onderaanneming'] + $commitments['onderaanneming']),
                'note' => $this->purchaseNote($purchases['count_onderaanneming'], $commitments['onderaanneming']),
            ],
            [
                'name' => 'Overig',
                'budgeted' => $totals->categories['overig'],
                'actual' => $purchases['overig'],
                'expected' => max($totals->categories['overig'], $purchases['overig']),
                'note' => $purchases['overig'] > 0 ? 'Uit inkoopfacturen' : 'Nog niet apart geboekt',
            ],
        ];

        $rows = array_map(fn (array $row) => [
            ...$row,
            'budgeted' => round($row['budgeted'], 2),
            'actual' => round($row['actual'], 2),
            'expected' => round($row['expected'], 2),
            'variance' => $row['budgeted'] > 0
                ? round(($row['expected'] - $row['budgeted']) / $row['budgeted'] * 100, 1)
                : null,
        ], $rows);

        $budgeted = array_sum(array_column($rows, 'budgeted'));
        $actual = array_sum(array_column($rows, 'actual'));
        $expected = array_sum(array_column($rows, 'expected'));
        $value = $totals->contractSum();

        return [
            'budget' => $budget,
            'totals' => $totals,
            'rows' => $rows,
            'budgeted' => round($budgeted, 2),
            'actual' => round($actual, 2),
            'expected' => round($expected, 2),
            'variance' => $budgeted > 0 ? round(($expected - $budgeted) / $budgeted * 100, 1) : 0.0,
            'value' => $value,
            'margin_budgeted' => $value > 0 ? round(($value - $budgeted) / $value * 100, 1) : 0.0,
            'margin_expected' => $value > 0 ? round(($value - $expected) / $value * 100, 1) : 0.0,
            'hours_budgeted' => $totals->hours,
            'hours_actual' => $labour['hours'],
            'hours_on_order' => $labour['budget_hours'],
            'efficiency' => round($efficiency, 3),
            'progress' => (int) $project->progress,
            'work_orders' => $labour['orders'],
        ];
    }

    /**
     * Leerpunten over projecten heen: per rol het verschil tussen begrote en
     * geboekte uren. Alleen rollen met minstens twee bonnen en een afwijking van
     * acht procent of meer, want onder die grens is het ruis.
     *
     * @return Collection<int, array{role: string, budgeted: float, actual: float, variance: float, orders: int}>
     */
    public function lessons(): Collection
    {
        return WorkOrder::approved()
            ->where('budget_hours', '>', 0)
            ->with('user')
            ->get()
            ->groupBy(fn (WorkOrder $order) => $order->user?->job_title
                ?: ($order->user?->role->label() ?? 'Onbekend'))
            ->map(fn (Collection $orders, string $role) => [
                'role' => $role,
                'budgeted' => round((float) $orders->sum('budget_hours'), 2),
                'actual' => round((float) $orders->sum('hours'), 2),
                'variance' => round(
                    ((float) $orders->sum('hours') - (float) $orders->sum('budget_hours'))
                    / (float) $orders->sum('budget_hours') * 100,
                    1,
                ),
                'orders' => $orders->count(),
            ])
            ->filter(fn (array $row) => $row['orders'] >= 2 && abs($row['variance']) >= 8)
            ->sortByDesc(fn (array $row) => abs($row['variance']))
            ->values();
    }

    /** De begroting waar we tegen afzetten: de laatste definitieve, anders de laatste. */
    private function budgetFor(Project $project): ?Budget
    {
        return Budget::where('project_id', $project->id)
            ->orderByRaw('case when status = ? then 0 else 1 end', [Budget::STATUS_FINAL])
            ->latest('id')
            ->first();
    }

    /**
     * @return array{hours: float, budget_hours: float, cost: float, orders: int}
     */
    private function labour(Project $project, Budget $budget): array
    {
        $orders = WorkOrder::approved()->where('project_id', $project->id)->with('user')->get();
        $fallbackRate = (float) $budget->hourly_rate;

        return [
            'hours' => round((float) $orders->sum('hours'), 2),
            'budget_hours' => round((float) $orders->sum('budget_hours'), 2),
            'cost' => round($orders->sum(fn (WorkOrder $order) => (float) $order->hours
                * ($order->hourly_rate !== null ? (float) $order->hourly_rate : ($order->user?->rate() ?? $fallbackRate))), 2),
            'orders' => $orders->count(),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function purchases(Project $project): array
    {
        $invoices = PurchaseInvoice::booked()->where('project_id', $project->id)->get();
        $out = [];

        foreach (['materiaal', 'materieel', 'onderaanneming', 'overig'] as $category) {
            $matching = $invoices->where('category', $category);
            $out[$category] = round((float) $matching->sum('amount'), 2);
            $out['count_'.$category] = $matching->count();
        }

        return $out;
    }

    /**
     * Besteld maar nog niet gefactureerd: dat komt nog, dus hoort het in het
     * verwachte eindbeeld.
     *
     * @return array<string, float>
     */
    private function openCommitments(Project $project): array
    {
        $orders = PurchaseOrder::uninvoiced()
            ->where('project_id', $project->id)
            ->where('status', '!=', PurchaseOrder::STATUS_DRAFT)
            ->with('lines')
            ->get();

        $out = ['materiaal' => 0.0, 'materieel' => 0.0, 'onderaanneming' => 0.0, 'overig' => 0.0];

        foreach ($orders as $order) {
            $key = array_key_exists($order->category, $out) ? $order->category : 'overig';
            $out[$key] += $order->total();
        }

        return array_map(fn (float $value) => round($value, 2), $out);
    }

    /**
     * De gemeten efficiëntie, gewogen naar hoeveel uren er al geboekt zijn. Eén
     * betekent precies op begroting; boven één betekent dat het werk meer uren
     * kost dan gedacht.
     *
     * @param  array{hours: float, budget_hours: float}  $labour
     */
    private function efficiency(array $labour, float $budgetedHours): float
    {
        if ($labour['budget_hours'] <= 0) {
            return 1.0;
        }

        $measured = $labour['hours'] / $labour['budget_hours'];
        $weight = $budgetedHours > 0
            ? min(1.0, $labour['budget_hours'] / (self::FULL_WEIGHT_AT * $budgetedHours))
            : 0.0;

        return 1 + ($measured - 1) * $weight;
    }

    private function hours(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',').' uur';
    }

    private function purchaseNote(int $count, float $commitment): string
    {
        $parts = [];

        if ($count > 0) {
            $parts[] = $count.' '.($count === 1 ? 'inkoopfactuur' : 'inkoopfacturen');
        }

        if ($commitment > 0) {
            $parts[] = 'EUR '.number_format($commitment, 0, ',', '.').' besteld, nog niet gefactureerd';
        }

        return $parts === [] ? 'Nog niets geboekt' : implode(', ', $parts);
    }
}
