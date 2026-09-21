<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\QuoteStatus;
use App\Enums\TimelineEventType;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(QuoteStatus::class)],
        ]);

        $status = $request->filled('status') ? QuoteStatus::from($request->query('status')) : null;

        $quotes = Quote::with(['customer', 'lead'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('quotes.index', [
            'quotes' => $quotes,
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        $lead = $request->filled('aanvraag') ? Lead::with('customer')->find($request->integer('aanvraag')) : null;

        return view('quotes.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'city']),
            'lead' => $lead,
        ]);
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $quote = DB::transaction(function () use ($validated) {
            $quote = Quote::create([
                'number' => Quote::nextNumber(),
                'customer_id' => $validated['customer_id'],
                'lead_id' => $validated['lead_id'] ?? null,
                'status' => QuoteStatus::Concept,
                'valid_until' => $validated['valid_until'] ?? now()->addDays(30)->toDateString(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncLines($quote, $validated['lines']);

            if ($quote->lead !== null && ! in_array($quote->lead->status, [LeadStatus::Akkoord, LeadStatus::Project], true)) {
                $quote->lead->update(['status' => LeadStatus::Offerte]);
            }

            $quote->customer->recordEvent(
                TimelineEventType::Offerte,
                "Offerte {$quote->number} aangemaakt",
                null,
                $quote,
            );

            AuditLog::record($quote, 'aangemaakt', [], ['total' => $quote->total]);

            return $quote;
        });

        return redirect()->route('quotes.show', $quote)->with('success', "Offerte {$quote->number} aangemaakt.");
    }

    public function show(Quote $quote): View
    {
        $quote->load(['lines', 'customer', 'lead', 'project']);

        return view('quotes.show', ['quote' => $quote]);
    }

    public function edit(Quote $quote): View|RedirectResponse
    {
        if ($quote->status !== QuoteStatus::Concept) {
            return redirect()->route('quotes.show', $quote)
                ->with('error', 'Alleen conceptoffertes kunnen worden bewerkt.');
        }

        $quote->load(['lines', 'customer']);

        return view('quotes.edit', ['quote' => $quote]);
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): RedirectResponse
    {
        if ($quote->status !== QuoteStatus::Concept) {
            return redirect()->route('quotes.show', $quote)
                ->with('error', 'Alleen conceptoffertes kunnen worden bewerkt.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($quote, $validated) {
            $quote->update([
                'valid_until' => $validated['valid_until'] ?? $quote->valid_until,
                'notes' => $validated['notes'] ?? null,
            ]);

            $quote->lines()->delete();
            $this->syncLines($quote, $validated['lines']);
        });

        return redirect()->route('quotes.show', $quote)->with('success', 'Offerte bijgewerkt.');
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        if ($quote->status !== QuoteStatus::Concept) {
            return redirect()->route('quotes.show', $quote)
                ->with('error', 'Alleen conceptoffertes kunnen worden verwijderd.');
        }

        $quote->delete();

        return redirect()->route('quotes.index')->with('success', 'Offerte verwijderd.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncLines(Quote $quote, array $lines): void
    {
        foreach (array_values($lines) as $index => $line) {
            $quote->lines()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit' => $line['unit'],
                'unit_price' => $line['unit_price'],
                'vat_rate' => $line['vat_rate'],
                'total' => round((float) $line['quantity'] * (float) $line['unit_price'], 2),
                'position' => $index,
            ]);
        }

        $quote->recalculateTotals();
    }
}
