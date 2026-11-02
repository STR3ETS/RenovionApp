<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\QuoteStatus;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Het financiële beeld van het bedrijf: omzet, werkvoorraad, debiteuren,
 * crediteuren en een cashflowprognose voor zes maanden.
 *
 * De prognose is een inschatting, geen boekhouding. Inkomsten komen uit
 * verzonden facturen op hun vervaldatum en uit termijnen die nog gefactureerd
 * moeten worden; uitgaven uit openstaande inkoopfacturen en bestellingen.
 */
class FinanceOverview
{
    /** Hoeveel maanden de prognose vooruit kijkt. */
    private const HORIZON = 6;

    /** Dagen tussen het factureren van een termijn en het binnenkomen van het geld. */
    private const COLLECTION_DAYS = 14;

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $invoices = Invoice::revenue()->with('project')->get();
        $outstanding = $invoices->filter(fn (Invoice $invoice) => $invoice->status->isOutstanding());

        $months = $this->months();
        $revenue = $this->revenuePerMonth($invoices, $months);

        $projects = Project::active()->with('customer')->get();
        $uninvoiced = $this->uninvoicedTerms($projects);

        $purchases = PurchaseInvoice::unpaid()->with('project')->get();
        $commitments = PurchaseOrder::uninvoiced()
            ->where('status', '!=', PurchaseOrder::STATUS_DRAFT)
            ->with('lines')
            ->get();

        return [
            'months' => $months,
            'revenue_per_month' => $revenue,
            'revenue_month' => $revenue[array_key_last($revenue)] ?? 0.0,
            'revenue_year' => round($invoices
                ->filter(fn (Invoice $i) => $i->date->year === now()->year)
                ->sum(fn (Invoice $i) => $i->signedAmount()), 2),
            'order_book' => $this->orderBook($projects),
            'uninvoiced' => round($uninvoiced->sum('amount'), 2),
            'open_quotes' => $this->openQuotes(),
            'pipeline' => $this->pipeline(),
            'debtors' => round($outstanding->sum(fn (Invoice $i) => $i->openAmount()), 2),
            'debtors_overdue' => round($outstanding->filter->isOverdue()->sum(fn (Invoice $i) => $i->openAmount()), 2),
            'debtor_invoices' => $outstanding->sortBy('due_date')->values(),
            'creditors' => round($purchases->sum(fn (PurchaseInvoice $i) => $i->total()), 2),
            'creditors_overdue' => round($purchases->filter->isOverdue()->sum(fn (PurchaseInvoice $i) => $i->total()), 2),
            'creditor_invoices' => $purchases->sortBy('due_date')->values(),
            'commitments' => round($commitments->sum(fn (PurchaseOrder $o) => $o->total()), 2),
            'cashflow' => $this->cashflow($months, $outstanding, $uninvoiced, $purchases, $commitments),
        ];
    }

    /**
     * De maanden van de prognose, van deze maand vooruit.
     *
     * @return list<string>
     */
    private function months(): array
    {
        return collect(range(0, self::HORIZON - 1))
            ->map(fn (int $i) => now()->startOfMonth()->addMonths($i)->format('Y-m'))
            ->all();
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @param  list<string>  $months
     * @return array<string, float>
     */
    private function revenuePerMonth(Collection $invoices, array $months): array
    {
        // Zes maanden terug tot en met deze maand, zodat je de lijn ziet lopen.
        $past = collect(range(-5, 0))
            ->map(fn (int $i) => now()->startOfMonth()->addMonths($i)->format('Y-m'));

        return $past->mapWithKeys(fn (string $month) => [
            $month => round($invoices
                ->filter(fn (Invoice $i) => $i->date->format('Y-m') === $month)
                ->sum(fn (Invoice $i) => $i->signedAmount()), 2),
        ])->all();
    }

    /**
     * De werkvoorraad: wat er aan opdrachten ligt minus wat al gefactureerd is.
     *
     * @param  Collection<int, Project>  $projects
     */
    private function orderBook(Collection $projects): float
    {
        return round($projects->sum(function (Project $project) {
            $invoiced = (float) Invoice::revenue()->where('project_id', $project->id)->sum('amount');

            return max(0, (float) $project->value - $invoiced);
        }), 2);
    }

    /**
     * Termijnen uit de begroting die nog niet gefactureerd zijn, met de datum
     * waarop ze naar verwachting aan de beurt zijn.
     *
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, array{project: Project, amount: float, due: Carbon}>
     */
    private function uninvoicedTerms(Collection $projects): Collection
    {
        $out = collect();

        foreach ($projects as $project) {
            $budget = Budget::where('project_id', $project->id)
                ->where('status', Budget::STATUS_FINAL)
                ->with('terms')
                ->latest('id')
                ->first();

            if ($budget === null) {
                continue;
            }

            $totals = $budget->totals();
            $invoiced = Invoice::where('project_id', $project->id)
                ->whereNotNull('budget_term_id')
                ->pluck('budget_term_id');

            foreach ($budget->terms as $index => $term) {
                if ($invoiced->contains($term->id)) {
                    continue;
                }

                $out->push([
                    'project' => $project,
                    'term' => $term,
                    'amount' => $budget->termAmount($term, $totals),
                    'due' => $term->due_date ?? $this->spreadDate($project, $index, $budget->terms->count()),
                ]);
            }
        }

        return $out;
    }

    /**
     * Zonder vaste datum verdelen we de termijnen gelijkmatig over de looptijd
     * van het project. Dat is een aanname, maar wel een zichtbare.
     */
    private function spreadDate(Project $project, int $index, int $count): Carbon
    {
        $start = $project->start_date ?? today();
        $end = $project->end_date_expected ?? $start->copy()->addDays(60);
        $span = max(1, (int) $start->diffInDays($end));

        return $start->copy()->addDays((int) round($span * ($index + 1) / max(1, $count)));
    }

    private function openQuotes(): float
    {
        // Alles wat bij de klant ligt en nog niet beslist is.
        return round((float) Quote::whereIn('status', [
            QuoteStatus::Verstuurd,
            QuoteStatus::Bekeken,
            QuoteStatus::Opvolgen,
        ])->sum('total'), 2);
    }

    /**
     * De pipeline gewogen naar kans. Een aanvraag die net binnen is telt voor
     * tien procent mee, een offerte die uit staat voor zestig.
     */
    private function pipeline(): float
    {
        $weights = [
            LeadStatus::Nieuw->value => 0.10,
            LeadStatus::Contact->value => 0.25,
            LeadStatus::Intake->value => 0.40,
            LeadStatus::Offerte->value => 0.60,
        ];

        return round(Lead::open()->get()->sum(function (Lead $lead) use ($weights) {
            $weight = $weights[$lead->status->value] ?? 0.0;

            return (float) ($lead->value ?? 0) * $weight;
        }), 2);
    }

    /**
     * De cashflow per maand: wat er binnenkomt, wat eruit gaat en het saldo.
     *
     * @param  list<string>  $months
     * @param  Collection<int, Invoice>  $outstanding
     * @param  Collection<int, array<string, mixed>>  $uninvoiced
     * @param  Collection<int, PurchaseInvoice>  $purchases
     * @param  Collection<int, PurchaseOrder>  $commitments
     * @return list<array{month: string, label: string, in: float, out: float, net: float}>
     */
    private function cashflow(array $months, Collection $outstanding, Collection $uninvoiced, Collection $purchases, Collection $commitments): array
    {
        $in = array_fill_keys($months, 0.0);
        $out = array_fill_keys($months, 0.0);

        // Een datum die al voorbij is, valt in de eerste maand van de prognose.
        $bucket = function (?Carbon $date) use ($months): ?string {
            if ($date === null) {
                return null;
            }

            $key = $date->isBefore(now()->startOfMonth()) ? $months[0] : $date->format('Y-m');

            return in_array($key, $months, true) ? $key : null;
        };

        foreach ($outstanding as $invoice) {
            if ($key = $bucket($invoice->due_date)) {
                $in[$key] += $invoice->openAmount();
            }
        }

        foreach ($uninvoiced as $term) {
            if ($key = $bucket($term['due']->copy()->addDays(self::COLLECTION_DAYS))) {
                $in[$key] += $term['amount'];
            }
        }

        foreach ($purchases as $invoice) {
            if ($key = $bucket($invoice->due_date)) {
                $out[$key] += $invoice->total();
            }
        }

        foreach ($commitments as $order) {
            if ($key = $bucket($order->expected_on ?? $order->ordered_on)) {
                $out[$key] += $order->total();
            }
        }

        return collect($months)->map(fn (string $month) => [
            'month' => $month,
            'label' => Carbon::createFromFormat('Y-m', $month)->translatedFormat('M'),
            'in' => round($in[$month], 2),
            'out' => round($out[$month], 2),
            'net' => round($in[$month] - $out[$month], 2),
        ])->all();
    }
}
