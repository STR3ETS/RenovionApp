<?php

namespace App\Http\Controllers;

use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\QualityCheck;
use App\Models\QualityReview;
use App\Models\SnagItem;
use App\Models\Subcontractor;
use App\Models\User;
use App\Support\QualityTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kwaliteit en oplevering: acht stappen met een harde voorwaarde per stap, de
 * controlepunten van het vak, en de restpuntenlijst.
 */
class QualityController extends Controller
{
    public function index(Request $request): View
    {
        $projects = Project::active()
            ->with(['customer', 'snags'])
            // Een uitvoerder ziet alleen de projecten waar hij op staat.
            ->when($request->user()->cannot('manage-crm'), fn ($query) => $query
                ->whereHas('craftsmen', fn ($sub) => $sub->whereKey($request->user()->id)))
            ->orderBy('end_date_expected')
            ->get();

        $reviews = QualityReview::whereIn('project_id', $projects->pluck('id'))
            ->with('checks')
            ->get()
            ->keyBy('project_id');

        return view('quality.index', [
            'rows' => $projects->map(fn (Project $project) => [
                'project' => $project,
                'review' => $reviews->get($project->id),
                'openSnags' => $project->snags->whereIn('status', [SnagItem::STATUS_OPEN, SnagItem::STATUS_IN_PROGRESS]),
            ]),
            'flow' => QualityTemplates::FLOW,
            'openSnagsTotal' => SnagItem::open()->count(),
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        abort_unless($project->isAccessibleBy($request->user()), 403);

        $review = QualityReview::forProject($project);
        $review->load(['checks.checker', 'project.snags.assignee', 'project.snags.subcontractor']);

        return view('quality.show', [
            'project' => $project,
            'review' => $review,
            'flow' => QualityTemplates::FLOW,
            'snags' => $project->snags()->with(['assignee', 'subcontractor', 'photos'])->latest()->get(),
            'people' => User::query()->where('active', true)->orderBy('name')->get(),
            'partners' => Subcontractor::available()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Een controlepunt afvinken, of weer open zetten. */
    public function toggleCheck(Request $request, QualityCheck $check): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        if ($check->isChecked()) {
            $check->update(['checked_at' => null, 'checked_by' => null]);

            return back()->with('success', 'Controlepunt weer open gezet.');
        }

        $check->update(['checked_at' => now(), 'checked_by' => $request->user()->id]);

        return back()->with('success', 'Controlepunt afgevinkt.');
    }

    /**
     * Een stap in de oplevering afronden. Lukt alleen als de voorwaarde klopt,
     * en de reden staat in het bericht zodat je niet hoeft te zoeken.
     */
    public function advance(Request $request, QualityReview $review): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $review->load(['checks', 'project.snags']);
        $blocker = $review->blockerFor($review->step);

        if ($blocker !== '') {
            return back()->with('error', $blocker);
        }

        $step = min(count(QualityTemplates::FLOW) - 1, $review->step + 1);
        $review->update(['step' => $step]);

        AuditLog::record($review, 'opleverstap afgerond', ['stap' => $review->step - 1], [
            'stap' => QualityTemplates::FLOW[$review->step - 1][0],
            'volgende' => QualityTemplates::FLOW[$step][0] ?? null,
        ]);

        // De laatste stap zet het project op opgeleverd met de garantietermijn.
        if ($step === count(QualityTemplates::FLOW) - 1) {
            $review->project->update([
                'end_date_actual' => $review->project->end_date_actual ?? today(),
                'warranty_months' => QualityTemplates::warrantyMonths($review->project->type),
            ]);
        }

        return back()->with('success', 'Stap afgerond: '.QualityTemplates::FLOW[$review->step - 1][0].'.');
    }

    /** Het opleverformulier laten tekenen door de klant. */
    public function sign(Request $request, QualityReview $review): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $validated = $request->validate([
            'signed_name' => ['required', 'string', 'max:120'],
        ]);

        $review->update([...$validated, 'signed_at' => now()]);

        AuditLog::record($review, 'opleverformulier getekend', [], [
            'naam' => $validated['signed_name'],
            'ip' => $request->ip(),
        ]);

        $review->project->customer?->recordEvent(
            TimelineEventType::Document,
            'Opleverformulier getekend door '.$validated['signed_name'],
            null,
            $review,
        );

        return back()->with('success', 'Opleverformulier getekend.');
    }

    public function storeSnag(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isAccessibleBy($request->user()), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'subcontractor_id' => ['nullable', 'exists:subcontractors,id'],
            'due_on' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'in:intern,klant'],
        ]);

        $snag = $project->snags()->create([
            ...$validated,
            'quality_review_id' => QualityReview::forProject($project)->id,
            'status' => SnagItem::STATUS_OPEN,
            'source' => $validated['source'] ?? 'intern',
        ]);

        AuditLog::record($snag, 'restpunt vastgelegd', [], [
            'punt' => $snag->title,
            'locatie' => $snag->location,
        ]);

        return back()->with('success', 'Restpunt vastgelegd.');
    }

    public function updateSnag(Request $request, SnagItem $snag): RedirectResponse
    {
        abort_unless($snag->project->isAccessibleBy($request->user()), 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', 'in:open,bezig,hersteld,geaccepteerd'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'subcontractor_id' => ['nullable', 'exists:subcontractors,id'],
            'due_on' => ['nullable', 'date'],
        ]);

        if (in_array($validated['status'] ?? '', [SnagItem::STATUS_RESOLVED, SnagItem::STATUS_ACCEPTED], true)) {
            $validated['resolved_at'] = $snag->resolved_at ?? now();
            $validated['resolved_by'] = $snag->resolved_by ?? $request->user()->id;
        }

        $old = $snag->only(array_keys($validated));
        $snag->update($validated);

        AuditLog::record($snag, 'restpunt bijgewerkt', $old, $validated);

        return back()->with('success', 'Restpunt bijgewerkt.');
    }

    public function destroySnag(Request $request, SnagItem $snag): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $snag->delete();

        return back()->with('success', 'Restpunt verwijderd.');
    }
}
