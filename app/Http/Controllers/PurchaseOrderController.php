<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BudgetLine;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inkoop: bestellingen bij leveranciers en de facturen die erop volgen. Een
 * bestelling die nog niet gefactureerd is, is een verplichting die meetelt in
 * de nacalculatie.
 */
class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = PurchaseOrder::with(['project', 'lines'])
            ->when($request->integer('project'), fn ($q, $id) => $q->where('project_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $invoices = PurchaseInvoice::with(['project', 'order'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [PurchaseInvoice::STATUS_NEW])
            ->orderByDesc('date')
            ->limit(25)
            ->get();

        return view('purchase-orders.index', [
            'orders' => $orders,
            'invoices' => $invoices,
            'projects' => Project::active()->orderBy('name')->get(['id', 'name']),
            'projectFilter' => $request->integer('project'),
            'status' => $request->string('status')->toString(),
            'open' => round(PurchaseOrder::uninvoiced()
                ->where('status', '!=', PurchaseOrder::STATUS_DRAFT)
                ->with('lines')->get()
                ->sum(fn (PurchaseOrder $o) => $o->total()), 2),
            'toApprove' => PurchaseInvoice::where('status', PurchaseInvoice::STATUS_NEW)->count(),
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['project.customer', 'lines.priceItem', 'invoices', 'creator']);

        return view('purchase-orders.show', ['order' => $purchaseOrder]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'supplier' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:120'],
            'category' => ['required', 'string', 'in:materiaal,materieel,onderaanneming,overig'],
            'chapter_code' => ['nullable', 'string', 'max:8'],
            'expected_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = PurchaseOrder::create([
            ...$validated,
            'number' => PurchaseOrder::nextNumber(),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'ordered_on' => today(),
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record($order, 'inkooporder aangemaakt', [], ['leverancier' => $order->supplier]);

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', "Inkooporder {$order->number} aangemaakt.");
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'supplier' => ['sometimes', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'string', 'in:concept,verzonden,bevestigd,geleverd'],
            'category' => ['sometimes', 'string', 'in:materiaal,materieel,onderaanneming,overig'],
            'expected_on' => ['nullable', 'date'],
            'delivered_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Geleverd melden zonder datum: dan is het vandaag.
        if (($validated['status'] ?? null) === PurchaseOrder::STATUS_DELIVERED && $purchaseOrder->delivered_on === null) {
            $validated['delivered_on'] = $validated['delivered_on'] ?? today();
        }

        $old = $purchaseOrder->only(array_keys($validated));
        $purchaseOrder->update($validated);

        AuditLog::record($purchaseOrder, 'inkooporder bijgewerkt', $old, $validated);

        return back()->with('success', 'Inkooporder bijgewerkt.');
    }

    public function storeLine(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:24'],
            'unit' => ['nullable', 'string', 'max:12'],
            'unit_price' => ['nullable', 'string', 'max:24'],
            'price_item_id' => ['nullable', 'exists:price_items,id'],
        ]);

        $purchaseOrder->lines()->create([
            'description' => $validated['description'],
            'quantity' => BudgetLine::parseNumber($validated['quantity'] ?? 1) ?: 1,
            'unit' => $validated['unit'] ?? 'st',
            'unit_price' => BudgetLine::parseNumber($validated['unit_price'] ?? 0),
            'price_item_id' => $validated['price_item_id'] ?? null,
            'position' => (int) $purchaseOrder->lines()->max('position') + 1,
        ]);

        return back()->with('success', 'Regel toegevoegd.');
    }

    public function destroyLine(PurchaseOrderLine $line): RedirectResponse
    {
        $line->delete();

        return back()->with('success', 'Regel verwijderd.');
    }
}
