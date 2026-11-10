<?php

namespace App\Http\Controllers;

use App\Actions\AwardRfq;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Project;
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
        $rfqs = Rfq::with(['project.customer', 'bids.subcontractor', 'winner', 'invited'])
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
        $rfq->load(['project.customer', 'invited.documents', 'bids.subcontractor', 'winner', 'creator']);

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
        $validated = $request->validate([
            'subcontractor_id' => ['required', 'exists:subcontractors,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'can_start_on' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'completeness' => ['nullable', 'integer', 'min:0', 'max:100'],
            'conditions' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        RfqBid::updateOrCreate(
            ['rfq_id' => $rfq->id, 'subcontractor_id' => $validated['subcontractor_id']],
            [...$validated, 'completeness' => $validated['completeness'] ?? 100, 'received_on' => today()],
        );

        return back()->with('success', 'Bieding vastgelegd.');
    }

    public function award(Request $request, Rfq $rfq, RfqBid $bid, AwardRfq $action): RedirectResponse
    {
        abort_unless($bid->rfq_id === $rfq->id, 404);

        try {
            $message = $action->handle($rfq, $bid, $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
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
