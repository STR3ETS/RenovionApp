<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InvoiceController as WebInvoiceController;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facturatie in de app (invoices/index en invoices/show): kerncijfers, termijnen die klaarstaan, de lijst per
 * status en de factuur zelf. Factureren, versturen, betaald melden en een concept verwijderen gaan via de
 * InvoiceController van de website (JSON voor de app), zodat de regels op één plek staan.
 */
class InvoiceController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): JsonResponse
    {
        $filter = $request->string('status')->toString();
        $filter = InvoiceStatus::tryFrom($filter) !== null ? $filter : '';

        $invoices = Invoice::with(['project', 'customer'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [InvoiceStatus::Verzonden->value])
            ->orderByDesc('date')
            ->when($filter !== '', fn ($query) => $query->where('status', $filter))
            ->paginate(self::PER_PAGE);

        $counts = Invoice::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $outstanding = Invoice::outstanding()->get();
        $ready = WebInvoiceController::readyTerms();
        $num = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

        return response()->json([
            'kpis' => [
                'outstanding' => round($outstanding->sum(fn (Invoice $i) => $i->openAmount()), 2),
                'overdue' => round($outstanding->filter->isOverdue()->sum(fn (Invoice $i) => $i->openAmount()), 2),
                'ready' => round((float) $ready->sum('amount'), 2),
                'ready_count' => $ready->count(),
                'paid' => (int) ($counts[InvoiceStatus::Betaald->value] ?? 0),
                'total' => (int) $counts->sum(),
            ],
            'ready' => $ready->map(fn (array $row) => [
                'term' => $row['term']->id,
                'title' => $row['term']->description,
                'sub' => collect([$row['budget']->project?->name, $num($row['term']->percentage).'% van de aanneemsom'])->filter()->join(', '),
                'amount' => round((float) $row['amount'], 2),
            ])->values(),
            'filter' => $filter,
            // Dezelfde tabs als de website: Alles, en elke status die voorkomt (of gekozen is).
            'filters' => collect(InvoiceStatus::cases())
                ->filter(fn (InvoiceStatus $status) => ($counts[$status->value] ?? 0) > 0 || $filter === $status->value)
                ->map(fn (InvoiceStatus $status) => ['value' => $status->value, 'label' => $status->label(), 'count' => (int) ($counts[$status->value] ?? 0)])
                ->prepend(['value' => '', 'label' => 'Alles', 'count' => (int) $counts->sum()])
                ->values(),
            'invoices' => collect($invoices->items())->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'title' => $invoice->description,
                'customer' => $invoice->customer->name,
                'status' => ['label' => $invoice->status->label(), 'classes' => $invoice->status->appBadgeClasses()],
                'type' => ['label' => $invoice->type->label(), 'classes' => $invoice->type->appBadgeClasses()],
                'overdue' => $invoice->isOverdue(),
                'due' => $invoice->isOverdue()
                    ? $invoice->daysOverdue().' dagen over de vervaldatum'
                    : 'vervalt '.$invoice->due_date->translatedFormat('j M'),
                'total' => $invoice->total(),
                'path' => route('invoices.show', $invoice, false),
            ]),
            'page' => $invoices->currentPage(),
            'last_page' => $invoices->lastPage(),
        ]);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['project.customer', 'customer', 'lines', 'term.budget']);
        $num = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

        return response()->json([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'title' => $invoice->description,
            'subtitle' => $invoice->customer->name.($invoice->project ? ', '.$invoice->project->name : ''),
            'status' => ['value' => $invoice->status->value, 'label' => $invoice->status->label(), 'classes' => $invoice->status->appBadgeClasses()],
            'type' => ['label' => $invoice->type->label(), 'classes' => $invoice->type->appBadgeClasses()],
            'lines' => $invoice->lines->map(fn (InvoiceLine $line) => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => trim($num($line->quantity).' '.$line->unit),
                'price' => (float) $line->unit_price,
                'total' => $line->total(),
            ]),
            'subtotal' => (float) $invoice->amount,
            'vat_label' => 'Btw '.$num($invoice->vat_pct).'%',
            'vat' => $invoice->vatAmount(),
            'retention' => (float) $invoice->retention,
            'total' => $invoice->total(),
            'dates' => array_values(array_filter([
                ['label' => 'Factuurdatum', 'value' => $invoice->date->translatedFormat('j M Y')],
                ['label' => 'Vervaldatum', 'value' => $invoice->due_date->translatedFormat('j M Y'), 'late' => $invoice->isOverdue()],
                $invoice->paid_at ? ['label' => 'Betaald op', 'value' => $invoice->paid_at->translatedFormat('j M Y'), 'paid' => true] : null,
            ])),
            // Dezelfde knoppen als de website: een concept versturen of verwijderen, een verzonden factuur betaald melden.
            'can' => [
                'send' => $invoice->status === InvoiceStatus::Concept,
                'paid' => $invoice->status === InvoiceStatus::Verzonden,
                'delete' => $invoice->status === InvoiceStatus::Concept,
            ],
            'term' => $invoice->term ? [
                'description' => $invoice->term->description,
                'budget' => $invoice->term->budget->number,
                'path' => route('budgets.show', $invoice->term->budget, false),
            ] : null,
            // Terug naar het project (kruimelpad in de app), zoals bij elk scherm dat bij een project hoort.
            'project' => $invoice->project ? ['name' => $invoice->project->name, 'path' => route('projects.show', $invoice->project, false)] : null,
            'links' => [
                'customer' => route('customers.show', $invoice->customer, false),
                'project' => $invoice->project ? route('projects.show', $invoice->project, false) : null,
                'web' => route('invoices.show', $invoice, false),
            ],
        ]);
    }
}
