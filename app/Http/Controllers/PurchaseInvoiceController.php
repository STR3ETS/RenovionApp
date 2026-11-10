<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PurchaseInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Inkoopfacturen. Pas na goedkeuring tellen ze mee als werkelijke kosten in de
 * nacalculatie, zodat een foutieve factuur het projectresultaat niet vervuilt.
 */
class PurchaseInvoiceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:80'],
            'supplier' => ['required', 'string', 'max:160'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'category' => ['required', 'string', 'in:materiaal,materieel,onderaanneming,overig'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'vat_pct' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Dezelfde leverancier mag niet twee keer hetzelfde factuurnummer sturen.
        if (PurchaseInvoice::where('supplier', $validated['supplier'])->where('number', $validated['number'])->exists()) {
            return back()->with('error', 'Deze leverancier heeft dat factuurnummer al ingediend.');
        }

        $invoice = PurchaseInvoice::create([
            ...$validated,
            'due_date' => $validated['due_date'] ?? Carbon::parse($validated['date'])->addDays(30),
            'vat_pct' => $validated['vat_pct'] ?? 21,
            'status' => PurchaseInvoice::STATUS_NEW,
        ]);

        AuditLog::record($invoice, 'inkoopfactuur geboekt', [], [
            'leverancier' => $invoice->supplier,
            'bedrag' => (float) $invoice->amount,
        ]);

        return back()->with('success', "Inkoopfactuur {$invoice->number} geboekt, wacht op goedkeuring.");
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:nieuw,goedgekeurd,betaald,afgewezen'],
            'category' => ['sometimes', 'string', 'in:materiaal,materieel,onderaanneming,overig'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'due_date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $old = $purchaseInvoice->only(array_keys($validated));

        if (($validated['status'] ?? null) === PurchaseInvoice::STATUS_APPROVED) {
            $validated['approved_at'] = now();
            $validated['approved_by'] = $request->user()->id;
        }

        if (($validated['status'] ?? null) === PurchaseInvoice::STATUS_PAID) {
            $validated['paid_at'] = now();
            $validated['approved_at'] = $purchaseInvoice->approved_at ?? now();
            $validated['approved_by'] = $purchaseInvoice->approved_by ?? $request->user()->id;
        }

        $purchaseInvoice->update($validated);

        AuditLog::record($purchaseInvoice, 'inkoopfactuur bijgewerkt', $old, $validated);

        return back()->with('success', 'Inkoopfactuur bijgewerkt.');
    }

    public function destroy(PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        abort_if($purchaseInvoice->isBooked(), 422, 'Een geboekte factuur kan niet weg. Zet hem op afgewezen.');

        $purchaseInvoice->delete();

        return back()->with('success', 'Inkoopfactuur verwijderd.');
    }
}
