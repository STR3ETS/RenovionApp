<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Support\App\Badges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inkoop in de app (purchase-orders/index en purchase-orders/show): bestellingen, inkoopfacturen met
 * goedkeuren en betaald melden, factuur boeken, en de bestelling met regels en levering. Alle wijzigingen gaan
 * via PurchaseOrderController en PurchaseInvoiceController van de website (JSON voor de app).
 */
class PurchaseOrderController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): JsonResponse
    {
        $orders = PurchaseOrder::with(['project', 'lines'])
            ->when($request->integer('project'), fn ($q, $id) => $q->where('project_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(self::PER_PAGE);

        $invoices = PurchaseInvoice::with(['project', 'order'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [PurchaseInvoice::STATUS_NEW])
            ->orderByDesc('date')
            ->limit(25)
            ->get();

        return response()->json([
            'kpis' => [
                'open' => round(PurchaseOrder::uninvoiced()
                    ->where('status', '!=', PurchaseOrder::STATUS_DRAFT)
                    ->with('lines')->get()
                    ->sum(fn (PurchaseOrder $o) => $o->total()), 2),
                'to_approve' => PurchaseInvoice::where('status', PurchaseInvoice::STATUS_NEW)->count(),
                'orders' => $orders->total(),
            ],
            'orders' => collect($orders->items())->map(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'supplier' => $order->supplier,
                'status' => ['label' => $order->statusLabel(), 'classes' => Badges::purchaseOrder($order)],
                'late' => $order->isLate(),
                'sub' => $order->number.', '.$order->project->name.', '
                    .($order->expected_on ? 'levering '.$order->expected_on->translatedFormat('j M') : 'geen leverdatum'),
                'total' => $order->total(),
                'path' => route('purchase-orders.show', $order, false),
            ]),
            'page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'invoices' => $invoices->map(fn (PurchaseInvoice $invoice) => $this->invoice($invoice)),
            'form' => [
                'projects' => Project::active()->orderBy('name')->get(['id', 'name'])->map(fn (Project $project) => ['id' => $project->id, 'name' => $project->name]),
                'categories' => collect(PurchaseInvoice::CATEGORIES)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
                'today' => today()->toDateString(),
            ],
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $order = $purchaseOrder->load(['project.customer', 'lines.priceItem', 'invoices', 'creator']);
        $num = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

        return response()->json([
            'id' => $order->id,
            'number' => $order->number,
            'supplier' => $order->supplier,
            'subtitle' => $order->project->name.', '.(PurchaseInvoice::CATEGORIES[$order->category] ?? $order->category),
            'status' => ['value' => $order->status, 'label' => $order->statusLabel(), 'classes' => Badges::purchaseOrder($order)],
            // De andere statussen, zoals de knoppen in de kop van de website.
            'statuses' => collect(PurchaseOrder::STATUSES)
                ->except($order->status)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'lines' => $order->lines->map(fn (PurchaseOrderLine $line) => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => trim($num($line->quantity).' '.$line->unit),
                'price' => (float) $line->unit_price,
                'total' => $line->total(),
            ]),
            'total' => $order->total(),
            'delivery' => array_values(array_filter([
                ['label' => 'Besteld op', 'value' => $order->ordered_on?->translatedFormat('j M Y') ?? 'niet vastgelegd'],
                ['label' => 'Verwacht', 'value' => $order->expected_on?->translatedFormat('j M Y') ?? 'onbekend', 'late' => $order->isLate()],
                $order->delivered_on ? ['label' => 'Geleverd', 'value' => $order->delivered_on->translatedFormat('j M Y'), 'done' => true] : null,
            ])),
            'invoices' => $order->invoices->map(fn (PurchaseInvoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => ['label' => $invoice->statusLabel(), 'classes' => Badges::purchaseInvoice($invoice)],
                'total' => $invoice->total(),
            ]),
            'notes' => $order->notes,
            'project' => ['name' => $order->project->name, 'path' => route('projects.show', $order->project, false)],
            'links' => [
                'project' => route('projects.show', $order->project, false),
                'web' => route('purchase-orders.show', $order, false),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function invoice(PurchaseInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'supplier' => $invoice->supplier,
            'status' => ['value' => $invoice->status, 'label' => $invoice->statusLabel(), 'classes' => Badges::purchaseInvoice($invoice)],
            'sub' => $invoice->number.', '.$invoice->categoryLabel().', '.($invoice->project?->name ?? 'geen project').', '
                .($invoice->isOverdue() ? 'te laat' : 'vervalt '.$invoice->due_date->translatedFormat('j M')),
            'overdue' => $invoice->isOverdue(),
            'total' => $invoice->total(),
            // Dezelfde knoppen als de website: een nieuwe factuur goedkeuren of afwijzen, een goedgekeurde betaald melden.
            'actions' => match ($invoice->status) {
                PurchaseInvoice::STATUS_NEW => [
                    ['value' => PurchaseInvoice::STATUS_APPROVED, 'label' => 'Goedkeuren', 'primary' => true],
                    ['value' => PurchaseInvoice::STATUS_DISPUTED, 'label' => 'Afwijzen', 'primary' => false],
                ],
                PurchaseInvoice::STATUS_APPROVED => [
                    ['value' => PurchaseInvoice::STATUS_PAID, 'label' => 'Betaald melden', 'primary' => false],
                ],
                default => [],
            },
        ];
    }
}
