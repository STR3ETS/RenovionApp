<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\QualityCheck;
use App\Models\QualityReview;
use App\Models\SnagItem;
use App\Models\Subcontractor;
use App\Models\User;
use App\Support\QualityTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kwaliteit en oplevering in de app (quality/index en quality/show): de acht stappen, de controlepunten
 * en de restpunten, met dezelfde toegang als de website (uitvoerders alleen eigen projecten, bekijken).
 * Afvinken, doorzetten, tekenen en restpunten gaan via de QualityController van de website (JSON-antwoord).
 */
class QualityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $flow = QualityTemplates::FLOW;

        $projects = Project::active()
            ->with(['customer', 'snags'])
            // Een uitvoerder ziet alleen de projecten waar hij op staat.
            ->when($user->cannot('manage-crm'), fn ($query) => $query
                ->whereHas('craftsmen', fn ($sub) => $sub->whereKey($user->id)))
            ->orderBy('end_date_expected')
            ->get();

        $reviews = QualityReview::whereIn('project_id', $projects->pluck('id'))->get()->keyBy('project_id');

        return response()->json([
            'open_snags' => SnagItem::open()->count(),
            'projects' => $projects->map(function (Project $project) use ($reviews, $flow) {
                $review = $reviews->get($project->id);
                $step = $review?->step ?? 0;

                return [
                    'id' => $project->id,
                    'path' => route('quality.show', $project, false),
                    'name' => $project->name,
                    'sub' => collect([$project->customer?->name, 'voortgang '.$project->progress.'%', 'stap '.($step + 1).' van '.count($flow).': '.$flow[$step][0]])->filter()->join(', '),
                    'open_snags' => $project->snags->whereIn('status', [SnagItem::STATUS_OPEN, SnagItem::STATUS_IN_PROGRESS])->count(),
                    'wkb' => (bool) $review?->wkb,
                    'step' => $step,
                ];
            })->values(),
            'steps' => count($flow),
        ]);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->isAccessibleBy($request->user()), 403);

        $crm = $request->user()->can('manage-crm');
        $flow = QualityTemplates::FLOW;
        $review = QualityReview::forProject($project);
        $review->load(['checks.checker', 'project.snags']);
        $blocker = $review->blockerFor($review->step);
        $checks = $review->applicableChecks();
        $snags = $project->snags()->with(['assignee', 'subcontractor'])->latest()->get();

        return response()->json([
            'project' => ['id' => $project->id, 'name' => $project->name, 'path' => route('projects.show', $project, false)],
            'subtitle' => collect([$project->customer?->name, 'voortgang '.$project->progress.'%', $review->wkb ? 'valt onder de Wkb' : null])->filter()->join(', '),
            'review_id' => $review->id,
            'step' => $review->step,
            'steps' => count($flow),
            'delivered' => $review->isDelivered(),
            'blocker' => $blocker,
            'advance' => ($flow[$review->step][0] ?? '').' afronden',
            'flow' => collect($flow)->map(fn (array $item, int $index) => [
                'label' => $item[0],
                'condition' => $item[1],
                'state' => $index < $review->step ? 'done' : ($index === $review->step ? 'current' : 'todo'),
            ])->values(),
            'checks' => $checks->map(fn (QualityCheck $check) => [
                'id' => $check->id,
                'title' => $check->title,
                'checked' => $check->isChecked(),
                'meta' => $check->isChecked() ? collect([$check->checker?->name, $check->checked_at->translatedFormat('j M H:i')])->filter()->join(', ') : null,
                'wkb' => (bool) $check->wkb,
            ])->values(),
            'checks_done' => $checks->filter->isChecked()->count(),
            'snags' => $snags->map(fn (SnagItem $snag) => [
                'id' => $snag->id,
                'title' => $snag->title,
                'status' => ['value' => $snag->status, 'label' => $snag->statusLabel(), 'classes' => $snag->appBadgeClasses()],
                'customer' => $snag->source === 'klant',
                'sub' => ($snag->location ?: 'geen locatie').', '.$snag->ownerName().($snag->due_on ? ', voor '.$snag->due_on->translatedFormat('j M') : ''),
                'description' => $snag->description,
                'overdue' => $snag->isOverdue(),
            ])->values(),
            'snags_open' => $snags->filter->isOpen()->count(),
            'snag_statuses' => collect(SnagItem::STATUSES)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'signed' => $review->signed_at ? ['name' => $review->signed_name, 'at' => $review->signed_at->translatedFormat('j F Y H:i')] : null,
            'can_manage' => $crm,
            // Restpunten vastleggen en toewijzen mag alleen de projectleiding (routes/web.php, can:manage-crm).
            'people' => $crm ? User::query()->where('active', true)->orderBy('name')->get(['id', 'name']) : [],
            'partners' => $crm ? Subcontractor::available()->orderBy('name')->get(['id', 'name']) : [],
            'delivered_text' => $project->end_date_actual
                ? 'Opgeleverd '.$project->end_date_actual->translatedFormat('j M Y').', garantie '.$project->warranty_months.' maanden'
                : null,
            // Opleverrapport (stap 7, opleverdocumenten): opmaken of openen, zoals op het overzicht van het dossier.
            'report' => $project->deliveryReport ? [
                'path' => route('delivery-reports.show', $project->deliveryReport, false),
                'sub' => 'Opgemaakt '.$project->deliveryReport->generated_at->translatedFormat('j M Y').'. '
                    .($project->deliveryReport->client_signed_at ? 'Getekend door de klant.' : 'Nog niet getekend door de klant.'),
            ] : null,
            'links' => [
                'work_orders' => route('work-orders.index', ['project' => $project->id], false),
                'web' => route('quality.show', $project, false),
            ],
        ]);
    }
}
