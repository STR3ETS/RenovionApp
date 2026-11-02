<?php

namespace App\Http\Controllers;

use App\Actions\BuildFinalSettlement;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetTerm;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Facturatie: termijnfacturen uit de begroting, meerwerk en de eindafrekening.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('status')->toString();

        $query = Invoice::with(['project', 'customer'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [InvoiceStatus::Verzonden->value])
            ->orderByDesc('date');

        if ($filter !== '' && InvoiceStatus::tryFrom($filter) !== null) {
            $query->where('status', $filter);
        }

        $invoices = $query->paginate(25)->withQueryString();
        $outstanding = Invoice::outstanding()->get();

        return view('invoices.index', [
            'invoices' => $invoices,
            'filter' => $filter,
            'counts' => Invoice::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'outstanding' => round($outstanding->sum(fn (Invoice $i) => $i->openAmount()), 2),
            'overdue' => round($outstanding->filter->isOverdue()->sum(fn (Invoice $i) => $i->openAmount()), 2),
            // Termijnen die klaarstaan om te factureren.
            'ready' => $this->readyTerms(),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['project.customer', 'customer', 'lines', 'term.budget']);

        return view('invoices.show', ['invoice' => $invoice]);
    }

    /**
     * Een termijnfactuur maken. Het bedrag komt uit de termijn in de begroting,
     * zodat de aanneemsom en de facturen nooit uit elkaar lopen.
     */
    public function storeTerm(Request $request, BudgetTerm $term): RedirectResponse
    {
        $budget = $term->budget;
        $project = $budget->project;

        abort_if($project === null, 422, 'Deze begroting hangt nog niet aan een project.');

        if (Invoice::where('budget_term_id', $term->id)->exists()) {
            return back()->with('error', 'Deze termijn is al gefactureerd.');
        }

        $totals = $budget->totals();
        $amount = $budget->termAmount($term, $totals);

        $invoice = DB::transaction(function () use ($project, $term, $amount, $totals) {
            $invoice = Invoice::create([
                'number' => Invoice::nextNumber(),
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'budget_term_id' => $term->id,
                'type' => InvoiceType::Termijn,
                'description' => $term->description,
                'date' => today(),
                'due_date' => today()->addDays(Invoice::PAYMENT_DAYS),
                'amount' => $amount,
                'vat_pct' => $totals->vatPercentage,
                'status' => InvoiceStatus::Concept,
            ]);

            $invoice->lines()->create([
                'description' => $term->description.' ('.rtrim(rtrim(number_format((float) $term->percentage, 2, ',', '.'), '0'), ',').' procent van de aanneemsom)',
                'quantity' => 1,
                'unit' => 'pst',
                'unit_price' => $amount,
            ]);

            $term->update(['status' => BudgetTerm::STATUS_INVOICED]);

            return $invoice;
        });

        AuditLog::record($invoice, 'termijnfactuur aangemaakt', [], [
            'termijn' => $term->description,
            'bedrag' => $amount,
        ]);

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', "Factuur {$invoice->number} staat klaar als concept.");
    }

    /**
     * De eindafrekening: meer- en minderwerk verrekend, min wat er al
     * gefactureerd is, met eventueel vijf procent achtergehouden.
     */
    public function storeFinal(Request $request, Project $project, BuildFinalSettlement $action): RedirectResponse
    {
        $settlement = $action->preview($project);

        if ($settlement['existing'] !== null) {
            return redirect()
                ->route('invoices.show', $settlement['existing'])
                ->with('error', 'Er is al een eindafrekening voor dit project.');
        }

        $invoice = DB::transaction(function () use ($project, $settlement) {
            $invoice = Invoice::create([
                'number' => Invoice::nextNumber(),
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'type' => InvoiceType::Eindafrekening,
                'description' => 'Eindafrekening '.$project->name,
                'date' => today(),
                'due_date' => today()->addDays(Invoice::PAYMENT_DAYS),
                'amount' => $settlement['balance'],
                'vat_pct' => $settlement['vat_pct'],
                'retention' => $settlement['retention'],
                'status' => InvoiceStatus::Concept,
            ]);

            $position = 0;

            $invoice->lines()->create([
                'description' => 'Aanneemsom volgens overeenkomst',
                'unit_price' => $settlement['base'],
                'position' => $position++,
            ]);

            foreach ($settlement['extra_lines'] as $extra) {
                $invoice->lines()->create([
                    'description' => 'Meerwerk: '.$extra->description,
                    'quantity' => $extra->quantity,
                    'unit' => $extra->unit,
                    'unit_price' => (float) $extra->price,
                    'position' => $position++,
                ]);
            }

            foreach ($settlement['deductions'] as $deduction) {
                $invoice->lines()->create([
                    'description' => 'Minderwerk: '.$deduction->description,
                    'unit_price' => -1 * (float) $deduction->amount,
                    'position' => $position++,
                ]);
            }

            if ($settlement['invoiced'] > 0) {
                $invoice->lines()->create([
                    'description' => 'Reeds gefactureerd in termijnen',
                    'unit_price' => -1 * $settlement['invoiced'],
                    'position' => $position++,
                ]);
            }

            if ($settlement['retention'] > 0) {
                $project->update([
                    'retention_amount' => $settlement['retention'],
                    'retention_release_on' => $settlement['retention_release_on'],
                ]);
            }

            return $invoice;
        });

        AuditLog::record($invoice, 'eindafrekening opgesteld', [], [
            'aanneemsom' => $settlement['base'],
            'meerwerk' => $settlement['extras'],
            'minderwerk' => $settlement['deduction_total'],
            'slottermijn' => $settlement['balance'],
            'ingehouden' => $settlement['retention'],
        ]);

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', "Eindafrekening {$invoice->number} opgesteld.");
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', new Enum(InvoiceStatus::class)],
            'description' => ['sometimes', 'string', 'max:255'],
            'due_date' => ['sometimes', 'date'],
            'paid_amount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $old = $invoice->only(array_keys($validated));

        if (isset($validated['status'])) {
            $status = InvoiceStatus::from($validated['status']);

            if ($status === InvoiceStatus::Verzonden && $invoice->sent_at === null) {
                $validated['sent_at'] = now();
                $validated['date'] = today();
                $validated['due_date'] = today()->addDays(Invoice::PAYMENT_DAYS);
            }

            if ($status === InvoiceStatus::Betaald) {
                $validated['paid_at'] = now();
                $validated['paid_amount'] = $invoice->total();
                $invoice->term?->update(['status' => BudgetTerm::STATUS_PAID]);
            }
        }

        $invoice->update($validated);

        AuditLog::record($invoice, 'factuur bijgewerkt', $old, $validated);

        if (($validated['status'] ?? null) === InvoiceStatus::Verzonden->value) {
            $invoice->customer->recordEvent(
                TimelineEventType::Betaling,
                "Factuur {$invoice->number} verstuurd",
                $invoice->description.', EUR '.number_format($invoice->total(), 2, ',', '.'),
                $invoice,
            );
        }

        return back()->with('success', 'Factuur bijgewerkt.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->status !== InvoiceStatus::Concept, 422, 'Alleen een concept kan weg.');

        $invoice->term?->update(['status' => BudgetTerm::STATUS_PLANNED]);
        $number = $invoice->number;
        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', "Concept {$number} verwijderd.");
    }

    /**
     * Termijnen uit definitieve begrotingen die nog niet gefactureerd zijn.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function readyTerms(): Collection
    {
        $invoiced = Invoice::whereNotNull('budget_term_id')->pluck('budget_term_id');

        return Budget::where('status', Budget::STATUS_FINAL)
            ->whereNotNull('project_id')
            ->with(['terms', 'project.customer'])
            ->get()
            ->flatMap(function (Budget $budget) use ($invoiced) {
                $totals = $budget->totals();

                return $budget->terms
                    ->reject(fn (BudgetTerm $term) => $invoiced->contains($term->id))
                    ->map(fn (BudgetTerm $term) => [
                        'term' => $term,
                        'budget' => $budget,
                        'amount' => $budget->termAmount($term, $totals),
                    ]);
            })
            ->take(12);
    }
}
