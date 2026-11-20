<?php

namespace App\Support;

use App\Enums\QuoteStatus;
use App\Models\Budget;
use App\Models\BudgetTerm;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use Illuminate\Support\Collection;

/**
 * Het factuurplan van een project: wanneer factureren we welk deel van de
 * opdracht. Het komt uit de termijnen van de begroting; is er geen begroting,
 * dan uit de getekende offerte met het standaard betaalschema.
 */
final class InvoicePlan
{
    public const SOURCE_BUDGET = 'begroting';

    public const SOURCE_QUOTE = 'offerte';

    /**
     * @param  Collection<int, array{description: string, percentage: float, amount: float, term: ?BudgetTerm, invoice: ?Invoice}>  $rows
     */
    private function __construct(
        public readonly ?string $source,
        public readonly ?string $reference,
        public readonly float $base,
        public readonly Collection $rows,
        public readonly bool $invoiceable,
    ) {}

    public static function for(Project $project): self
    {
        $budget = Budget::where('project_id', $project->id)
            ->orderByRaw('case when status = ? then 0 else 1 end', [Budget::STATUS_FINAL])
            ->with(['terms', 'chapters.paragraphs.lines', 'markups', 'options'])
            ->latest('id')
            ->first();

        if ($budget !== null && $budget->terms->isNotEmpty()) {
            return self::fromBudget($project, $budget);
        }

        $quote = self::signedQuote($project);

        if ($quote !== null) {
            return self::fromQuote($quote);
        }

        return new self(null, null, 0.0, collect(), false);
    }

    private static function fromBudget(Project $project, Budget $budget): self
    {
        $totals = $budget->totals();
        $invoices = Invoice::whereIn('budget_term_id', $budget->terms->pluck('id'))->get()->keyBy('budget_term_id');

        $rows = $budget->terms->map(fn (BudgetTerm $term) => [
            'description' => $term->description,
            'percentage' => (float) $term->percentage,
            'amount' => $budget->termAmount($term, $totals),
            'term' => $term,
            'invoice' => $invoices->get($term->id),
        ]);

        // Factureren kan pas op een definitieve begroting: daarvoor kan het bedrag nog schuiven.
        return new self(self::SOURCE_BUDGET, $budget->number, $totals->contractSum(), $rows, ! $budget->isDraft());
    }

    private static function fromQuote(Quote $quote): self
    {
        $base = (float) $quote->subtotal;

        $rows = collect(BudgetChapterLibrary::defaultTerms())->map(fn (array $term) => [
            'description' => $term['description'],
            'percentage' => (float) $term['percentage'],
            'amount' => round($base * $term['percentage'] / 100, 2),
            'term' => null,
            'invoice' => null,
        ]);

        return new self(self::SOURCE_QUOTE, $quote->number, $base, $rows, false);
    }

    private static function signedQuote(Project $project): ?Quote
    {
        if ($project->quote?->status === QuoteStatus::Akkoord) {
            return $project->quote;
        }

        return $project->quotes()->where('status', QuoteStatus::Akkoord)->latest('id')->first();
    }

    public function isEmpty(): bool
    {
        return $this->rows->isEmpty();
    }

    public function invoicedAmount(): float
    {
        return round((float) $this->rows->sum(fn (array $row) => $row['invoice'] !== null ? $row['amount'] : 0), 2);
    }

    public function coverage(): float
    {
        return round((float) $this->rows->sum('percentage'), 2);
    }
}
