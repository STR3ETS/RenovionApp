<?php

namespace App\Http\Controllers;

use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetMarkup;
use App\Models\BudgetOption;
use App\Models\BudgetTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Wat naast de regels in een begroting staat: de staart (algemene kosten, winst
 * en risico, verzekering), de opties en het meerwerk, en het betaalschema.
 */
class BudgetExtrasController extends Controller
{
    public function storeMarkup(Request $request, Budget $budget): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'percentage' => ['required', 'string', 'max:12'],
        ]);

        $budget->markups()->create([
            'name' => $validated['name'],
            'percentage' => BudgetLine::parseNumber($validated['percentage']),
            'position' => (int) $budget->markups()->max('position') + 1,
        ]);

        return back()->with('success', 'Staartpost toegevoegd.');
    }

    public function updateMarkup(Request $request, BudgetMarkup $markup): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'percentage' => ['sometimes', 'string', 'max:12'],
        ]);

        $old = ['percentage' => (float) $markup->percentage];

        if (array_key_exists('percentage', $validated)) {
            $validated['percentage'] = BudgetLine::parseNumber($validated['percentage']);
        }

        $markup->update($validated);

        // Een wijziging in de staart verandert de aanneemsom, dus die leggen we vast.
        AuditLog::record($markup->budget, 'staart aangepast', $old, [
            'post' => $markup->name,
            'percentage' => (float) $markup->percentage,
        ]);

        return back()->with('success', 'Staartpost bijgewerkt.');
    }

    public function destroyMarkup(BudgetMarkup $markup): RedirectResponse
    {
        $markup->delete();

        return back()->with('success', 'Staartpost verwijderd.');
    }

    public function storeOption(Request $request, Budget $budget): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['nullable', 'string', 'max:24'],
            'unit' => ['nullable', 'string', 'max:12'],
            'price' => ['nullable', 'string', 'max:24'],
        ]);

        $budget->options()->create([
            'description' => $validated['description'],
            'note' => $validated['note'] ?? null,
            'quantity' => BudgetLine::parseNumber($validated['quantity'] ?? 1) ?: 1,
            'unit' => $validated['unit'] ?? 'pst',
            'price' => BudgetLine::parseNumber($validated['price'] ?? 0),
            'status' => BudgetOption::STATUS_OPEN,
            'position' => (int) $budget->options()->max('position') + 1,
        ]);

        return back()->with('success', 'Optie toegevoegd.');
    }

    /**
     * Een optie op akkoord zetten maakt er meerwerk van: het telt vanaf dat
     * moment mee in de aanneemsom, met dezelfde staart als het gewone werk.
     */
    public function updateOption(Request $request, BudgetOption $option): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['sometimes', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['sometimes', 'string', 'max:24'],
            'unit' => ['sometimes', 'string', 'max:12'],
            'price' => ['sometimes', 'string', 'max:24'],
            'status' => ['sometimes', 'string', 'in:optie,akkoord,afgewezen'],
        ]);

        foreach (['quantity', 'price'] as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = BudgetLine::parseNumber($validated[$field]);
            }
        }

        $oldStatus = $option->status;

        if (($validated['status'] ?? $oldStatus) !== $oldStatus) {
            $validated['decided_at'] = $validated['status'] === BudgetOption::STATUS_OPEN ? null : now();
        }

        $option->update($validated);

        if (($validated['status'] ?? $oldStatus) !== $oldStatus) {
            AuditLog::record($option->budget, 'optie besloten', ['status' => $oldStatus], [
                'optie' => $option->description,
                'status' => $option->status,
            ]);

            if ($option->status === BudgetOption::STATUS_APPROVED) {
                $option->budget->customer?->recordEvent(
                    TimelineEventType::Wijziging,
                    'Meerwerk akkoord: '.$option->description,
                    'Telt mee in de aanneemsom van begroting '.$option->budget->number.'.',
                    $option->budget,
                );
            }
        }

        return back()->with('success', 'Optie bijgewerkt.');
    }

    public function destroyOption(BudgetOption $option): RedirectResponse
    {
        $option->delete();

        return back()->with('success', 'Optie verwijderd.');
    }

    public function storeTerm(Request $request, Budget $budget): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'percentage' => ['nullable', 'string', 'max:12'],
            'amount' => ['nullable', 'string', 'max:24'],
            'fixed' => ['nullable', 'boolean'],
            'due_date' => ['nullable', 'date'],
        ]);

        $budget->terms()->create([
            'description' => $validated['description'],
            'percentage' => BudgetLine::parseNumber($validated['percentage'] ?? 0),
            'amount' => BudgetLine::parseNumber($validated['amount'] ?? 0),
            'fixed' => (bool) ($validated['fixed'] ?? false),
            'due_date' => $validated['due_date'] ?? null,
            'status' => BudgetTerm::STATUS_PLANNED,
            'position' => (int) $budget->terms()->max('position') + 1,
        ]);

        return back()->with('success', 'Termijn toegevoegd.');
    }

    public function updateTerm(Request $request, BudgetTerm $term): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['sometimes', 'string', 'max:255'],
            'percentage' => ['sometimes', 'string', 'max:12'],
            'amount' => ['sometimes', 'string', 'max:24'],
            'fixed' => ['nullable', 'boolean'],
            'status' => ['sometimes', 'string', 'in:gepland,vervallen,gefactureerd,betaald'],
            'due_date' => ['nullable', 'date'],
        ]);

        foreach (['percentage', 'amount'] as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = BudgetLine::parseNumber($validated[$field]);
            }
        }

        if ($request->has('fixed')) {
            $validated['fixed'] = $request->boolean('fixed');
        }

        $term->update($validated);

        return back()->with('success', 'Termijn bijgewerkt.');
    }

    public function destroyTerm(BudgetTerm $term): RedirectResponse
    {
        $term->delete();

        return back()->with('success', 'Termijn verwijderd.');
    }
}
