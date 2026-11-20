<?php

namespace App\Http\Controllers;

use App\Actions\AwardRfq;
use App\Enums\QuoteStatus;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\RfqBid;
use App\Models\Subcontractor;
use App\Support\BudgetChapterLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Prijsaanvragen: werk uitzetten bij onderaannemers, biedingen vergelijken en
 * gunnen. Het budget komt uit het hoofdstuk van de begroting, zodat je de
 * biedingen tegen een echt getal kunt afzetten.
 */
class RfqController extends Controller
{
    public function index(Request $request): View
    {
        $rfqs = Rfq::with(['project.customer', 'bids.subcontractor.documents', 'winner', 'invited'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('project'), fn ($q, $id) => $q->where('project_id', $id))
            ->latest('id')
            ->get();

        $committed = Rfq::committed()->with('bids')->get();

        return view('rfqs.index', [
            'rfqs' => $rfqs,
            'counts' => Rfq::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'status' => $request->string('status')->toString(),
            'projects' => Project::active()->orderBy('name')->get(['id', 'name']),
            'projectFilter' => $request->integer('project'),
            'commitment' => round($committed->sum(fn (Rfq $rfq) => (float) ($rfq->winningBid()?->price ?? 0)), 2),
            'savings' => round($committed->sum(fn (Rfq $rfq) => $rfq->savings() ?? 0), 2),
        ]);
    }

    public function create(Request $request): View
    {
        return view('rfqs.create', [
            'projects' => Project::active()->with('customer')->orderBy('name')->get(),
            'chapters' => BudgetChapterLibrary::CHAPTERS,
            'projectId' => $request->integer('project'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:160'],
            'chapter_code' => ['nullable', 'string', 'max:8'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'],
            'starts_on' => ['nullable', 'date'],
        ]);

        // Zonder opgegeven budget pakken we het hoofdstuktotaal uit de begroting.
        $budget = $validated['budget'] ?? $this->chapterTotal(
            (int) $validated['project_id'],
            $validated['chapter_code'] ?? null,
        );

        $rfq = Rfq::create([
            ...$validated,
            'number' => Rfq::nextNumber(),
            'budget' => $budget,
            'status' => Rfq::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record($rfq, 'prijsaanvraag aangemaakt', [], [
            'titel' => $rfq->title,
            'budget' => (float) $rfq->budget,
        ]);

        return redirect()->route('rfqs.show', $rfq)->with('success', "Prijsaanvraag {$rfq->number} aangemaakt.");
    }

    public function show(Rfq $rfq): View
    {
        $rfq->load(['project.customer', 'invited.documents', 'bids.subcontractor.documents', 'bids.purchaseOrder', 'winner', 'creator']);

        // Alleen partijen met een rond dossier kunnen we uitnodigen.
        $candidates = Subcontractor::available()
            ->with('documents')
            ->orderBy('name')
            ->get()
            ->reject(fn (Subcontractor $sub) => $rfq->invited->contains($sub));

        return view('rfqs.show', [
            'rfq' => $rfq,
            'candidates' => $candidates,
            'best' => $rfq->bestComparableBid(),
            // Wat we er zelf voor vragen is voor elk bod hetzelfde: het laatst ingevulde stelt het volgende voor.
            'lastSale' => $rfq->bids->whereNotNull('sale_price')->sortByDesc('updated_at')->first(),
            'canApproveLowMargin' => request()->user()->role->isManagement(),
        ]);
    }

    public function update(Request $request, Rfq $rfq): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'budget' => ['sometimes', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'],
            'starts_on' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'in:concept,uitgezet,gegund,opgeleverd'],
        ]);

        $old = $rfq->only(array_keys($validated));
        $rfq->update($validated);

        AuditLog::record($rfq, 'prijsaanvraag bijgewerkt', $old, $validated);

        return back()->with('success', 'Prijsaanvraag bijgewerkt.');
    }

    /**
     * Partijen uitnodigen en de aanvraag uitzetten. Een partij met een
     * onvolledig dossier weigeren we hier, niet pas bij het gunnen.
     */
    public function invite(Request $request, Rfq $rfq): RedirectResponse
    {
        $validated = $request->validate([
            'subcontractor_ids' => ['required', 'array', 'min:1'],
            'subcontractor_ids.*' => ['exists:subcontractors,id'],
        ]);

        $wanted = Subcontractor::with('documents')->findMany($validated['subcontractor_ids']);
        $allowed = $wanted->filter->mayBeInvited();
        $refused = $wanted->reject->mayBeInvited();

        foreach ($allowed as $subcontractor) {
            $rfq->invited()->syncWithoutDetaching([
                $subcontractor->id => ['invited_at' => now()],
            ]);
        }

        if ($allowed->isNotEmpty() && $rfq->status === Rfq::STATUS_DRAFT) {
            $rfq->update(['status' => Rfq::STATUS_SENT]);
        }

        AuditLog::record($rfq, 'prijsaanvraag uitgezet', [], [
            'uitgenodigd' => $allowed->pluck('name')->join(', '),
            'geweigerd' => $refused->pluck('name')->join(', ') ?: null,
        ]);

        $message = $allowed->count().' '.($allowed->count() === 1 ? 'partij' : 'partijen').' uitgenodigd.';

        if ($refused->isNotEmpty()) {
            $message .= ' Niet uitgenodigd omdat het dossier niet rond is: '.$refused->pluck('name')->join(', ').'.';
        }

        return back()->with($refused->isNotEmpty() ? 'error' : 'success', $message);
    }

    public function storeBid(Request $request, Rfq $rfq): RedirectResponse
    {
        // Ruimhartige invoer: bedragen mogen met komma, punt of valutateken.
        foreach (['price', 'sale_quantity', 'sale_price'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => BudgetLine::parseNumber($request->input($field))]);
            }
        }

        $validated = $request->validate([
            'subcontractor_id' => ['required', 'exists:subcontractors,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_quantity' => ['nullable', 'numeric', 'min:0'],
            'sale_unit' => ['nullable', 'string', 'max:20'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'via_whatsapp' => ['nullable', 'boolean'],
            'can_start_on' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'completeness' => ['nullable', 'integer', 'min:0', 'max:100'],
            'conditions' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        RfqBid::updateOrCreate(
            ['rfq_id' => $rfq->id, 'subcontractor_id' => $validated['subcontractor_id']],
            [
                ...$validated,
                'completeness' => $validated['completeness'] ?? 100,
                'via_whatsapp' => $request->boolean('via_whatsapp'),
                'received_on' => today(),
            ],
        );

        return back()->with('success', 'Bieding vastgelegd.');
    }

    public function award(Request $request, Rfq $rfq, RfqBid $bid, AwardRfq $action): RedirectResponse
    {
        abort_unless($bid->rfq_id === $rfq->id, 404);

        $validated = $request->validate(['margin_reason' => ['nullable', 'string', 'max:500']]);

        try {
            $message = $action->handle($rfq, $bid, $request->user(), $validated['margin_reason'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    /**
     * Het verkoopbedrag van een bod overnemen in onze eigen offerte aan de
     * klant. Kan alleen zolang die offerte nog een concept is: een verzonden
     * offerte verandert alleen via een nieuwe versie.
     */
    public function adopt(Rfq $rfq, RfqBid $bid): RedirectResponse
    {
        abort_unless($bid->rfq_id === $rfq->id, 404);

        if ($bid->saleTotal() === null) {
            return back()->with('error', 'Vul eerst in waarvoor we dit werk verkopen: hoeveelheid en prijs.');
        }

        $project = $rfq->project;

        $quote = Quote::query()
            ->where('status', QuoteStatus::Concept)
            ->where(fn ($query) => $query
                ->where('project_id', $project->id)
                ->when($project->lead_id !== null, fn ($q) => $q->orWhere('lead_id', $project->lead_id)))
            ->latest('id')
            ->first();

        if ($quote === null) {
            return back()->with('error', 'Er is geen conceptofferte voor dit project om het in over te nemen. Maak eerst een offerte of een nieuwe versie.');
        }

        $quantity = (float) ($bid->sale_quantity ?? 1);

        $quote->lines()->create([
            'description' => $rfq->title,
            'quantity' => $quantity,
            'unit' => $bid->sale_unit ?: 'post',
            'unit_price' => $bid->sale_price,
            'vat_rate' => 21,
            'total' => $bid->saleTotal(),
            'position' => (int) $quote->lines()->max('position') + 1,
        ]);

        $quote->unsetRelation('lines');
        $quote->recalculateTotals();

        AuditLog::record($quote, 'post overgenomen uit prijsaanvraag', [], [
            'prijsaanvraag' => $rfq->number,
            'partner' => $bid->subcontractor->name,
            'bedrag' => $bid->saleTotal(),
        ]);

        return back()->with('success', "Overgenomen in offerte {$quote->number}: {$rfq->title}, EUR ".number_format($bid->saleTotal(), 2, ',', '.').'.');
    }

    /**
     * Het totaal van een hoofdstuk uit de laatste definitieve begroting: dat is
     * het budget waartegen je de biedingen afzet.
     */
    private function chapterTotal(int $projectId, ?string $chapterCode): float
    {
        if ($chapterCode === null) {
            return 0.0;
        }

        $budget = Budget::where('project_id', $projectId)
            ->orderByRaw('case when status = ? then 0 else 1 end', [Budget::STATUS_FINAL])
            ->with('chapters.paragraphs.lines')
            ->latest('id')
            ->first();

        if ($budget === null) {
            return 0.0;
        }

        return collect($budget->totals()->chapters)
            ->firstWhere('code', $chapterCode)['total'] ?? 0.0;
    }
}
