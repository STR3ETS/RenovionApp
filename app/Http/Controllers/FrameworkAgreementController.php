<?php

namespace App\Http\Controllers;

use App\Actions\ApplyFrameworkPreset;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Customer;
use App\Models\FrameworkAgreement;
use App\Models\FrameworkPrice;
use App\Models\FrameworkTerm;
use App\Models\PriceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Raamovereenkomsten met zakelijke opdrachtgevers: looptijd, vaste afspraken
 * en vaste verkoopprijzen. De vaste prijzen vullen een nieuwe begroting voor
 * die klant, zodat niemand ze opnieuw hoeft op te zoeken.
 */
class FrameworkAgreementController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        if (! $customer->isBusiness()) {
            return back()->with('error', 'Een raamovereenkomst hoort bij een zakelijke klant. Zet de klant eerst op zakelijk.');
        }

        $validated = $this->validated($request);

        $agreement = $customer->frameworkAgreements()->create([
            ...$validated,
            'number' => FrameworkAgreement::nextNumber(),
            'preset_active' => $request->boolean('preset_active', true),
        ]);

        // De afspraak over betalen geldt vanaf nu ook voor de facturen van deze klant.
        $customer->update(['payment_days' => $agreement->payment_days]);

        AuditLog::record($agreement, 'raamovereenkomst vastgelegd', [], [
            'klant' => $customer->name,
            'nummer' => $agreement->number,
        ]);

        return back()->with('success', "Raamovereenkomst {$agreement->number} vastgelegd.");
    }

    public function update(Request $request, FrameworkAgreement $agreement): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['preset_active'] = $request->boolean('preset_active');

        $old = $agreement->only(array_keys($validated));
        $agreement->update($validated);
        $agreement->customer->update(['payment_days' => $agreement->payment_days]);

        AuditLog::record($agreement, 'raamovereenkomst bijgewerkt', $old, $validated);

        return back()->with('success', 'Raamovereenkomst bijgewerkt.');
    }

    public function destroy(FrameworkAgreement $agreement): RedirectResponse
    {
        AuditLog::record($agreement, 'raamovereenkomst verwijderd', ['nummer' => $agreement->number]);
        $agreement->delete();

        return back()->with('success', 'Raamovereenkomst verwijderd.');
    }

    public function storePrice(Request $request, FrameworkAgreement $agreement): RedirectResponse
    {
        // Ruimhartige invoer: "EUR 27,50" en "27.5" zijn hetzelfde bedrag.
        $request->merge(['fixed_price' => BudgetLine::parseNumber($request->input('fixed_price'))]);

        $validated = $request->validate([
            'price_item_id' => ['nullable', 'exists:price_items,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'fixed_price' => ['required', 'numeric', 'min:0.01'],
        ]);

        $item = isset($validated['price_item_id']) ? PriceItem::find($validated['price_item_id']) : null;

        if ($item === null && blank($validated['description'] ?? null)) {
            return back()->with('error', 'Kies een artikel uit de prijslijst of geef een omschrijving.');
        }

        $agreement->prices()->create([
            'price_item_id' => $item?->id,
            'description' => filled($validated['description'] ?? null) ? $validated['description'] : $item->name,
            'unit' => filled($validated['unit'] ?? null) ? $validated['unit'] : ($item?->unit ?? 'm2'),
            'fixed_price' => $validated['fixed_price'],
            'position' => (int) $agreement->prices()->max('position') + 1,
        ]);

        return back()->with('success', 'Vaste prijs toegevoegd.');
    }

    public function destroyPrice(FrameworkPrice $price): RedirectResponse
    {
        $price->delete();

        return back()->with('success', 'Vaste prijs verwijderd.');
    }

    public function storeTerm(Request $request, FrameworkAgreement $agreement): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $agreement->terms()->create([
            'body' => $validated['body'],
            'position' => (int) $agreement->terms()->max('position') + 1,
        ]);

        return back()->with('success', 'Afspraak toegevoegd.');
    }

    public function destroyTerm(FrameworkTerm $term): RedirectResponse
    {
        $term->delete();

        return back()->with('success', 'Afspraak verwijderd.');
    }

    /** De knop "Preset toepassen" op een bestaande begroting. */
    public function apply(Budget $budget, ApplyFrameworkPreset $action): RedirectResponse
    {
        $budget->loadMissing('customer.frameworkAgreements.prices.priceItem');
        $agreement = $budget->customer?->activeFramework();

        if ($agreement === null) {
            return back()->with('error', 'Deze klant heeft geen lopende raamovereenkomst.');
        }

        if (! $budget->isDraft()) {
            return back()->with('error', 'Een definitieve begroting staat op slot. Heropen hem eerst.');
        }

        $added = $action->handle($budget, $agreement);

        return back()->with('success', "Preset van {$agreement->number} toegepast: {$added} ".($added === 1 ? 'regel' : 'regels').' met vaste prijzen, hoeveelheid nog op nul.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'overhead_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'profit_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_days' => ['required', 'integer', 'min:0', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Vaste prijzen zijn verkoopprijzen, dus zonder opgave is de opslag nul.
        $validated['overhead_pct'] = $validated['overhead_pct'] ?? 0;
        $validated['profit_pct'] = $validated['profit_pct'] ?? 0;

        return $validated;
    }
}
