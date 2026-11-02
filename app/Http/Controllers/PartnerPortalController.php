<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Rfq;
use App\Models\RfqBid;
use App\Models\Subcontractor;
use App\Models\SubcontractorDocument;
use App\Models\WorkOrder;
use App\Support\CertificateCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het partnerportaal: wat een onderaannemer ziet als hij inlogt.
 *
 * Drie dingen, in deze volgorde: wat er van hem gevraagd wordt (prijsaanvragen),
 * wat hij doet (opdrachten en werkbonnen), en wat er nog aan zijn dossier
 * mankeert. Dat laatste bovenaan als het niet rond is, want zolang dat zo is
 * krijgt hij geen werk.
 */
class PartnerPortalController extends Controller
{
    public function index(Request $request): View
    {
        $partner = $this->partnerFor($request);
        $dossier = $partner->dossier();

        // Aanvragen waarvoor hij is uitgenodigd en nog niet geboden heeft.
        $invitations = $partner->load('bids')->bids->pluck('rfq_id');

        $open = Rfq::where('status', Rfq::STATUS_SENT)
            ->whereHas('invited', fn ($query) => $query->whereKey($partner->id))
            ->with(['project', 'bids' => fn ($query) => $query->where('subcontractor_id', $partner->id)])
            ->orderBy('deadline')
            ->get();

        return view('partner.index', [
            'partner' => $partner,
            'dossier' => $dossier,
            'invitations' => $open,
            'awarded' => Rfq::committed()
                ->where('awarded_to', $partner->id)
                ->with(['project', 'bids'])
                ->latest('awarded_on')
                ->get(),
            'workOrders' => $partner->user_id === null
                ? collect()
                : WorkOrder::where('user_id', $partner->user_id)->with('project')->latest('date')->limit(10)->get(),
            'documentTypes' => CertificateCatalogue::PARTNER_DOCUMENTS,
            'agreementTypes' => CertificateCatalogue::PARTNER_AGREEMENTS,
        ]);
    }

    /**
     * Een bieding doen op een aanvraag waarvoor hij is uitgenodigd.
     */
    public function bid(Request $request, Rfq $rfq): RedirectResponse
    {
        $partner = $this->partnerFor($request);

        abort_unless($rfq->invited()->whereKey($partner->id)->exists(), 403);
        abort_unless($rfq->status === Rfq::STATUS_SENT, 422, 'Deze aanvraag staat niet meer open.');

        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'can_start_on' => ['nullable', 'date', 'after_or_equal:today'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'completeness' => ['nullable', 'integer', 'min:0', 'max:100'],
            'conditions' => ['nullable', 'string', 'max:2000'],
        ]);

        $bid = RfqBid::updateOrCreate(
            ['rfq_id' => $rfq->id, 'subcontractor_id' => $partner->id],
            [...$validated, 'completeness' => $validated['completeness'] ?? 100, 'received_on' => today()],
        );

        AuditLog::record($bid, 'bieding ingediend via het partnerportaal', [], [
            'partner' => $partner->name,
            'prijs' => (float) $bid->price,
        ]);

        return back()->with('success', 'Bedankt, je bieding staat vast. Wij laten weten wat we besluiten.');
    }

    /**
     * Een dossierstuk aanleveren. De partner vult zijn eigen papieren aan; wij
     * controleren ze daarna.
     */
    public function document(Request $request, Subcontractor $subcontractor): RedirectResponse
    {
        $partner = $this->partnerFor($request);

        abort_unless($partner->id === $subcontractor->id, 403);

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:120'],
            'valid_until' => ['nullable', 'date'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $isAgreement = array_key_exists($validated['type'], CertificateCatalogue::PARTNER_AGREEMENTS);

        SubcontractorDocument::updateOrCreate(
            ['subcontractor_id' => $partner->id, 'type' => $validated['type']],
            [
                'valid_until' => $validated['valid_until'] ?? null,
                'signed_at' => $isAgreement ? now() : null,
                'signed_by' => $isAgreement ? $partner->contact_name : null,
                'path' => $request->file('file')?->store('partner-documents'),
            ],
        );

        $dossier = $partner->fresh()->dossier();

        return back()->with('success', $dossier['complete']
            ? 'Je dossier is compleet. Je kunt nu werk van ons krijgen.'
            : count($dossier['open']).' '.(count($dossier['open']) === 1 ? 'punt' : 'punten').' nog te gaan.');
    }

    /**
     * Welke partner hoort bij deze inlog? Een onderaannemer komt alleen bij zijn
     * eigen gegevens.
     */
    private function partnerFor(Request $request): Subcontractor
    {
        $partner = Subcontractor::with('documents')->firstWhere('user_id', $request->user()->id);

        abort_if($partner === null, 403, 'Dit account hoort niet bij een partner.');

        return $partner;
    }
}
