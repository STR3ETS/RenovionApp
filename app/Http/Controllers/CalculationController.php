<?php

namespace App\Http\Controllers;

use App\Enums\CalculationStatus;
use App\Enums\TimelineEventType;
use App\Http\Requests\StoreCalculationRequest;
use App\Models\AuditLog;
use App\Models\Calculation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\PriceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalculationController extends Controller
{
    public function index(): View
    {
        return view('calculations.index', [
            'calculations' => Calculation::with(['customer', 'lines'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function create(Request $request): View
    {
        $lead = $request->filled('aanvraag')
            ? Lead::with('customer')->find($request->integer('aanvraag'))
            : null;

        return view('calculations.create', [
            'lead' => $lead,
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'city']),
            'sources' => PriceItem::active()
                ->select('source', 'edition')
                ->distinct()
                ->orderBy('source')
                ->get(),
        ]);
    }

    public function store(StoreCalculationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $calculation = DB::transaction(function () use ($validated, $request) {
            $lead = isset($validated['lead_id']) ? Lead::find($validated['lead_id']) : null;

            $calculation = Calculation::create([
                'title' => $validated['title'],
                'customer_id' => $validated['customer_id'] ?? $lead?->customer_id,
                'lead_id' => $lead?->id,
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['lines'] ?? [] as $line) {
                $calculation->addLine($line);
            }

            $calculation->customer?->recordEvent(
                TimelineEventType::Calculatie,
                'Calculatie aangemaakt: '.$calculation->title,
                null,
                $calculation,
            );

            AuditLog::record($calculation, 'aangemaakt', [], ['regels' => count($validated['lines'] ?? [])]);

            return $calculation;
        });

        return redirect()
            ->route('calculations.show', $calculation)
            ->with('success', 'Calculatie aangemaakt — controleer de regels en stel bij waar nodig.');
    }

    public function show(Calculation $calculation): View
    {
        $calculation->load(['customer', 'lead', 'lines', 'creator']);

        return view('calculations.show', ['calculation' => $calculation]);
    }

    public function update(Request $request, Calculation $calculation): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'risk_pct' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'margin_pct' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'vat_pct' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', Rule::enum(CalculationStatus::class)],
        ]);

        $oldStatus = $calculation->status;
        $calculation->update($validated);

        if ($calculation->wasChanged('status')) {
            AuditLog::record($calculation, $calculation->status === CalculationStatus::Definitief ? 'definitief_gemaakt' : 'heropend', [
                'status' => $oldStatus->value,
            ], ['status' => $calculation->status->value]);
        } elseif ($calculation->wasChanged()) {
            AuditLog::record($calculation, 'bijgewerkt', [], $calculation->getChanges());
        }

        return redirect()->route('calculations.show', $calculation)->with('success', 'Calculatie bijgewerkt.');
    }

    public function destroy(Calculation $calculation): RedirectResponse
    {
        abort_if($calculation->isLocked(), 403, 'Een definitieve calculatie kan niet worden verwijderd.');

        AuditLog::record($calculation, 'verwijderd', ['title' => $calculation->title], []);
        $calculation->delete();

        return redirect()->route('calculations.index')->with('success', 'Calculatie verwijderd.');
    }
}
