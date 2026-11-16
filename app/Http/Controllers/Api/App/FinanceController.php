<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Services\CostReviewService;
use App\Services\FinanceOverview;
use App\Support\App\Badges;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Het financiële beeld (finance/index) en de nacalculatie (finance/cost-reviews) in de app. Dezelfde
 * berekeningen als de website (FinanceOverview, CostReviewService); de grafieken bouwt de app zelf als balken.
 */
class FinanceController extends Controller
{
    public function index(FinanceOverview $overview): JsonResponse
    {
        $f = $overview->build();

        return response()->json([
            'kpis' => [
                'order_book' => $f['order_book'],
                'revenue_year' => $f['revenue_year'],
                'uninvoiced' => $f['uninvoiced'],
                'debtors' => $f['debtors'],
                'debtors_overdue' => $f['debtors_overdue'],
                'creditors' => $f['creditors'],
                'creditors_overdue' => $f['creditors_overdue'],
                'commitments' => $f['commitments'],
                'pipeline' => $f['pipeline'],
                'open_quotes' => $f['open_quotes'],
            ],
            'revenue' => collect($f['revenue_per_month'])->map(fn (float $amount, string $month) => [
                'month' => $month,
                'label' => Carbon::createFromFormat('Y-m', $month)->translatedFormat('M'),
                'amount' => $amount,
            ])->values(),
            'cashflow' => $f['cashflow'],
            'debtors' => $f['debtor_invoices']->take(8)->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'title' => $invoice->customer->name,
                'sub' => $invoice->number.', '.($invoice->isOverdue()
                    ? $invoice->daysOverdue().' dagen te laat'
                    : 'vervalt '.$invoice->due_date->translatedFormat('j M')),
                'amount' => $invoice->openAmount(),
                'overdue' => $invoice->isOverdue(),
                'path' => route('invoices.show', $invoice, false),
            ])->values(),
            'creditors' => $f['creditor_invoices']->take(8)->map(fn (PurchaseInvoice $invoice) => [
                'id' => $invoice->id,
                'title' => $invoice->supplier,
                'sub' => $invoice->number.', '.$invoice->categoryLabel().', '.($invoice->isOverdue()
                    ? 'te laat'
                    : 'vervalt '.$invoice->due_date->translatedFormat('j M')),
                'amount' => $invoice->total(),
                'overdue' => $invoice->isOverdue(),
            ])->values(),
        ]);
    }

    public function costReviews(CostReviewService $service): JsonResponse
    {
        $num = fn (float $value, int $decimals = 1) => rtrim(rtrim(number_format($value, $decimals, ',', '.'), '0'), ',');
        $percent = fn (?float $value) => $value === null ? 'n.v.t.' : ($value > 0 ? '+' : '').$num($value).'%';

        $projects = Project::active()
            ->with('customer')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => ['project' => $project, 'review' => $service->for($project)])
            ->filter(fn (array $row) => $row['review'] !== null)
            ->values();

        return response()->json([
            'lessons' => $service->lessons()->map(fn (array $lesson) => [
                'role' => $lesson['role'],
                'sub' => $num($lesson['actual']).' geboekt tegenover '.$num($lesson['budgeted']).' begrote uren, over '.$lesson['orders'].' werkbonnen',
                'variance' => $percent($lesson['variance']),
                'classes' => Badges::variance($lesson['variance']),
            ])->values(),
            'projects' => $projects->map(function (array $row) use ($num, $percent) {
                $review = $row['review'];
                $project = $row['project'];

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'sub' => collect([
                        $project->customer?->name,
                        $review['budget']->number,
                        'voortgang '.$review['progress'].'%',
                        $num($review['hours_actual']).' van '.$num($review['hours_budgeted']).' uur geboekt',
                    ])->filter()->join(', '),
                    'budgeted' => $review['budgeted'],
                    'actual' => $review['actual'],
                    'expected' => $review['expected'],
                    'variance' => $percent($review['variance']),
                    'classes' => Badges::variance($review['variance']),
                    'rows' => array_map(fn (array $line) => [
                        'name' => $line['name'],
                        'budgeted' => $line['budgeted'],
                        'actual' => $line['actual'],
                        'expected' => $line['expected'],
                        'variance' => $percent($line['variance']),
                        'classes' => Badges::variance($line['variance']),
                        'note' => $line['note'],
                    ], $review['rows']),
                    'value' => $review['value'],
                    'margin_budgeted' => $num($review['margin_budgeted']).'%',
                    'margin_expected' => $num($review['margin_expected']).'%',
                    'margin_bad' => $review['margin_expected'] < $review['margin_budgeted'] - 2,
                    'efficiency' => $num($review['efficiency'], 2),
                    'path' => route('projects.show', $project, false),
                ];
            }),
        ]);
    }
}
