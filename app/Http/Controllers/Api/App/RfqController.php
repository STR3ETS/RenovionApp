<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RfqController as WebRfqController;
use App\Models\Project;
use App\Models\Rfq;
use App\Models\RfqBid;
use App\Models\Subcontractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Prijsaanvragen in de app (rfqs/index en rfqs/show): de lijst met verplichtingen en filters, en een aanvraag
 * met biedingen, gunnen, bieding vastleggen, partijen uitnodigen en de gegevens van de aanvraag. Uitnodigen,
 * bieden en gunnen gaan via de RfqController van de website (die geeft JSON aan de app); opslaan ook, via update().
 */
class RfqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Rfq::STATUSES))],
            'project' => ['nullable', 'integer'],
        ]);

        $status = $request->string('status')->toString();
        $projectId = $request->integer('project');

        $rfqs = Rfq::with(['project.customer', 'bids.subcontractor', 'winner', 'invited'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($projectId, fn ($q, $id) => $q->where('project_id', $id))
            ->latest('id')
            ->get();

        $committed = Rfq::committed()->with('bids')->get();
        $counts = Rfq::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $savings = round($committed->sum(fn (Rfq $rfq) => $rfq->savings() ?? 0), 2);

        $tabs = [['value' => '', 'label' => 'Alles', 'count' => (int) $counts->sum()]];

        foreach (Rfq::STATUSES as $key => $label) {
            if (($counts[$key] ?? 0) === 0 && $status !== $key) {
                continue;
            }

            $tabs[] = ['value' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0)];
        }

        return response()->json([
            'status' => $status,
            'project' => $projectId ?: null,
            'kpis' => [
                'commitment' => round($committed->sum(fn (Rfq $rfq) => (float) ($rfq->winningBid()?->price ?? 0)), 2),
                'savings' => abs($savings),
                'savings_label' => $savings >= 0 ? 'Onder begroting' : 'Boven begroting',
                'over' => $savings < 0,
                'sent' => (int) ($counts[Rfq::STATUS_SENT] ?? 0),
            ],
            'tabs' => $tabs,
            'projects' => Project::active()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Project $project) => ['id' => $project->id, 'name' => $project->name]),
            'rfqs' => $rfqs->map(function (Rfq $rfq) {
                $best = $rfq->bestComparableBid();
                $invited = $rfq->invited->count();
                $bids = $rfq->bids->count();

                return [
                    'id' => $rfq->id,
                    'title' => $rfq->title,
                    'sub' => $rfq->number.', '.$rfq->project->name.', '
                        .$invited.' '.($invited === 1 ? 'partij' : 'partijen').' uitgenodigd, '
                        .$bids.' '.($bids === 1 ? 'bieding' : 'biedingen'),
                    'status' => ['value' => $rfq->status, 'label' => $rfq->statusLabel(), 'classes' => $rfq->appBadgeClasses()],
                    'overdue' => $rfq->isOverdue(),
                    'budget' => (float) $rfq->budget,
                    'winner' => $rfq->winner ? [
                        'name' => $rfq->winner->name,
                        'price' => (float) ($rfq->winningBid()?->price ?? 0),
                        'under' => ($rfq->savings() ?? 0) >= 0,
                    ] : null,
                    'best' => $best ? (float) $best->price : null,
                    'path' => route('rfqs.show', $rfq, false),
                ];
            }),
            'links' => ['create' => route('rfqs.create', [], false)],
        ]);
    }

    public function show(Rfq $rfq): JsonResponse
    {
        return response()->json($this->payload($rfq));
    }

    /**
     * De gegevens van de aanvraag opslaan (budget, deadline, start, werkomschrijving) via dezelfde
     * RfqController@update als de website: zelfde validatie en audit. Daarna de verse aanvraag.
     */
    public function update(Request $request, Rfq $rfq): JsonResponse
    {
        app(WebRfqController::class)->update($request, $rfq);

        return response()->json([...$this->payload($rfq->fresh()), 'message' => 'Prijsaanvraag bijgewerkt.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Rfq $rfq): array
    {
        $rfq->load(['project.customer', 'invited.documents', 'bids.subcontractor.documents', 'bids.rfq', 'winner', 'creator']);

        $best = $rfq->bestComparableBid();
        $closed = in_array($rfq->status, [Rfq::STATUS_AWARDED, Rfq::STATUS_DONE], true);
        $euro = fn ($amount) => (float) $amount;
        $savings = $rfq->savings();

        // Alleen partijen met een rond dossier kunnen we uitnodigen (de rest staat er met een waarschuwing).
        $candidates = Subcontractor::available()
            ->with('documents')
            ->orderBy('name')
            ->get()
            ->reject(fn (Subcontractor $sub) => $rfq->invited->contains($sub))
            ->values();

        return [
            'id' => $rfq->id,
            'number' => $rfq->number,
            'title' => $rfq->title,
            'subtitle' => collect([
                $rfq->project->name,
                $rfq->chapter_code ? 'hoofdstuk '.$rfq->chapter_code : null,
                'begroot EUR '.number_format((float) $rfq->budget, 0, ',', '.'),
            ])->filter()->join(', '),
            'status' => ['value' => $rfq->status, 'label' => $rfq->statusLabel(), 'classes' => $rfq->appBadgeClasses()],
            'overdue' => $rfq->isOverdue(),
            'budget' => $euro($rfq->budget),
            'winner' => $rfq->winner ? [
                'name' => $rfq->winner->name,
                'price' => $euro($rfq->winningBid()?->price ?? 0),
                'savings' => abs($savings ?? 0),
                'under' => ($savings ?? 0) >= 0,
                'order_number' => $rfq->order_number,
                'awarded_on' => $rfq->awarded_on?->translatedFormat('j F Y'),
            ] : null,
            'can_award' => ! $closed,
            'can_bid' => $rfq->invited->isNotEmpty() && $rfq->status !== Rfq::STATUS_AWARDED,
            'can_invite' => $candidates->isNotEmpty() && $rfq->status !== Rfq::STATUS_AWARDED,
            'bids' => $rfq->bids->map(fn (RfqBid $bid) => [
                'id' => $bid->id,
                'partner' => $bid->subcontractor->name,
                'conditions' => $bid->conditions,
                'partner_issue' => $bid->subcontractor->mayBeInvited() ? null : $bid->subcontractor->statusLabel(),
                'price' => $euro($bid->price),
                'variance' => $bid->varianceAgainstBudget(),
                'start' => $bid->can_start_on?->translatedFormat('j M') ?? 'onbekend',
                'days' => $bid->days,
                'completeness' => (int) $bid->completeness,
                'complete' => $bid->isComplete(),
                'best' => $best?->id === $bid->id,
            ])->values(),
            'invited' => $rfq->invited->map(fn (Subcontractor $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'bid' => $rfq->bids->contains('subcontractor_id', $partner->id),
            ])->values(),
            'candidates' => $candidates->map(fn (Subcontractor $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'warning' => $partner->mayBeInvited() ? null : $partner->statusLabel(),
            ]),
            'form' => [
                'budget' => $rfq->budget !== null ? (float) $rfq->budget : null,
                'deadline' => $rfq->deadline?->toDateString(),
                'starts_on' => $rfq->starts_on?->toDateString(),
                'scope' => $rfq->scope,
            ],
            'scope' => $rfq->scope,
            'project' => ['name' => $rfq->project->name, 'path' => route('projects.show', $rfq->project, false)],
            'links' => [
                'web' => route('rfqs.show', $rfq, false),
                'project' => route('projects.show', $rfq->project, false),
            ],
        ];
    }
}
