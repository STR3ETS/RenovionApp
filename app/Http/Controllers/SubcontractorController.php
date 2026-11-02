<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Subcontractor;
use App\Models\SubcontractorDocument;
use App\Models\User;
use App\Support\CertificateCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Onderaannemers en hun dossier. Zolang het dossier niet rond is, krijgt een
 * partij geen uitnodiging voor werk en geen opdracht.
 */
class SubcontractorController extends Controller
{
    public function index(Request $request): View
    {
        $subcontractors = Subcontractor::with(['documents', 'certificates'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%")))
            ->when($request->string('vak')->toString(), fn ($q, $trade) => $q->whereJsonContains('trades', $trade))
            ->orderBy('name')
            ->get();

        $byStatus = $subcontractors->groupBy(fn (Subcontractor $sub) => $sub->status());

        return view('subcontractors.index', [
            'subcontractors' => $request->string('status')->toString() !== ''
                ? $byStatus->get($request->string('status')->toString(), collect())
                : $subcontractors,
            'counts' => $byStatus->map->count(),
            'total' => $subcontractors->count(),
            'status' => $request->string('status')->toString(),
            'zoek' => $request->string('q')->toString(),
            'trade' => $request->string('vak')->toString(),
            'trades' => CertificateCatalogue::TRADES,
        ]);
    }

    public function show(Subcontractor $subcontractor): View
    {
        $subcontractor->load(['documents', 'certificates', 'awardedRfqs.project', 'bids.rfq']);

        return view('subcontractors.show', [
            'subcontractor' => $subcontractor,
            'dossier' => $subcontractor->dossier(),
            'documentTypes' => CertificateCatalogue::PARTNER_DOCUMENTS,
            'agreementTypes' => CertificateCatalogue::PARTNER_AGREEMENTS,
            'trades' => CertificateCatalogue::TRADES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'coc_number' => ['nullable', 'string', 'max:40'],
            'trades' => ['nullable', 'array'],
            'trades.*' => ['string', 'max:60'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'region' => ['nullable', 'string', 'max:120'],
        ]);

        $subcontractor = Subcontractor::create($validated);

        AuditLog::record($subcontractor, 'onderaannemer aangemeld', [], ['naam' => $subcontractor->name]);

        return redirect()
            ->route('subcontractors.show', $subcontractor)
            ->with('success', "{$subcontractor->name} aangemeld. Vul het dossier aan voordat je werk uitzet.");
    }

    public function update(Request $request, Subcontractor $subcontractor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'coc_number' => ['nullable', 'string', 'max:40'],
            'vat_number' => ['nullable', 'string', 'max:40'],
            'trades' => ['nullable', 'array'],
            'trades.*' => ['string', 'max:60'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'region' => ['nullable', 'string', 'max:120'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'id_checked' => ['nullable', 'boolean'],
            'blocked' => ['nullable', 'boolean'],
            'blocked_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach (['id_checked', 'blocked'] as $flag) {
            if ($request->has($flag)) {
                $validated[$flag] = $request->boolean($flag);
            }
        }

        $old = $subcontractor->only(array_keys($validated));
        $subcontractor->update($validated);

        AuditLog::record($subcontractor, 'onderaannemer bijgewerkt', $old, $validated);

        return back()->with('success', 'Gegevens bijgewerkt.');
    }

    /**
     * Een dossierstuk vastleggen: een document met geldigheidsdatum, of een
     * overeenkomst die is ondertekend.
     */
    public function storeDocument(Request $request, Subcontractor $subcontractor): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:120'],
            'valid_until' => ['nullable', 'date'],
            'signed' => ['nullable', 'boolean'],
            'signed_by' => ['nullable', 'string', 'max:120'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $isAgreement = array_key_exists($validated['type'], CertificateCatalogue::PARTNER_AGREEMENTS);

        $document = SubcontractorDocument::updateOrCreate(
            ['subcontractor_id' => $subcontractor->id, 'type' => $validated['type']],
            [
                'valid_until' => $validated['valid_until'] ?? null,
                'signed_at' => $isAgreement || $request->boolean('signed') ? now() : null,
                'signed_by' => $validated['signed_by'] ?? $subcontractor->contact_name,
                'path' => $request->file('file')?->store('partner-documents'),
            ],
        );

        AuditLog::record($subcontractor, 'dossierstuk vastgelegd', [], [
            'stuk' => $document->type,
            'geldig_tot' => $document->valid_until?->toDateString(),
        ]);

        $dossier = $subcontractor->fresh()->dossier();

        return back()->with('success', $dossier['complete']
            ? "Dossier van {$subcontractor->name} is nu rond. Je kunt werk uitzetten."
            : count($dossier['open']).' '.(count($dossier['open']) === 1 ? 'punt' : 'punten').' nog open in het dossier.');
    }

    public function destroyDocument(SubcontractorDocument $document): RedirectResponse
    {
        $document->delete();

        return back()->with('success', 'Dossierstuk verwijderd.');
    }

    /**
     * Een inlog voor het partnerportaal. De partner levert daar zijn eigen
     * papieren aan en doet zijn biedingen, zonder in onze omgeving te komen.
     *
     * Zonder mailserver verstuurt het systeem niets, dus het wachtwoord staat
     * eenmalig in de melding. Daarna is het niet meer op te vragen.
     */
    public function storePortalAccount(Request $request, Subcontractor $subcontractor): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        if (blank($subcontractor->email)) {
            return back()->with('error', 'Vul eerst een e-mailadres in bij deze partner.');
        }

        $password = Str::password(14);

        if ($subcontractor->user !== null) {
            $subcontractor->user->update(['password' => $password]);

            AuditLog::record($subcontractor->user, 'partnerportaal wachtwoord opnieuw gezet', [], [
                'partner' => $subcontractor->name,
            ]);

            return back()->with('success', "Nieuw wachtwoord voor {$subcontractor->name}: {$password}. Geef dit eenmalig door, het wordt niet nog eens getoond.");
        }

        if (User::where('email', $subcontractor->email)->exists()) {
            return back()->with('error', 'Er bestaat al een account met dit e-mailadres.');
        }

        $user = User::create([
            'name' => $subcontractor->contact_name ?: $subcontractor->name,
            'email' => $subcontractor->email,
            'role' => UserRole::Onderaannemer,
            'password' => $password,
            'plannable' => false,
        ]);

        $subcontractor->update(['user_id' => $user->id]);

        AuditLog::record($user, 'partnerportaal account aangemaakt', [], ['partner' => $subcontractor->name]);

        return back()->with('success', 'Account aangemaakt. Inloggen op '.config('app.url')." met {$subcontractor->email} en wachtwoord: {$password}. Geef dit eenmalig door.");
    }
}
