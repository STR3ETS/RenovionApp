<?php

namespace App\Http\Controllers;

use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\Project;
use App\Models\WorkPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Foto-bewijs (briefing §9): uploads gekoppeld aan fase, werkpakket en/of
 * checklistitem. Uitvoerders uploaden vanaf de bouwplaats op eigen projecten.
 */
class PhotoController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isAccessibleBy($request->user()), 403);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:20'],
            'photos.*' => ['image', 'max:10240'],
            'project_phase_id' => ['nullable', Rule::exists('project_phases', 'id')->where('project_id', $project->id)],
            'work_package_id' => ['nullable', Rule::exists('work_packages', 'id')->where('project_id', $project->id)],
            'checklist_item_id' => ['nullable', 'exists:checklist_items,id'],
            'caption' => ['nullable', 'string', 'max:255'],
            'client_visible' => ['nullable', 'boolean'],
        ]);

        $workPackage = isset($validated['work_package_id'])
            ? WorkPackage::find($validated['work_package_id'])
            : null;

        // Checklistitem moet bij het werkpakket horen.
        if (isset($validated['checklist_item_id'])
            && $workPackage?->items()->whereKey($validated['checklist_item_id'])->doesntExist()) {
            abort(422, 'Checklistitem hoort niet bij dit werkpakket.');
        }

        foreach ($validated['photos'] as $file) {
            $project->photos()->create([
                'project_phase_id' => $validated['project_phase_id'] ?? $workPackage?->project_phase_id,
                'work_package_id' => $workPackage?->id,
                'checklist_item_id' => $validated['checklist_item_id'] ?? null,
                'uploaded_by' => $request->user()->id,
                'path' => $file->store('project-photos'),
                'caption' => $validated['caption'] ?? null,
                'client_visible' => (bool) ($validated['client_visible'] ?? true),
            ]);
        }

        $count = count($validated['photos']);

        AuditLog::record($project, 'fotos_toegevoegd', [], [
            'aantal' => $count,
            'werkpakket' => $workPackage?->name,
        ]);
        $project->customer->recordEvent(
            TimelineEventType::Document,
            $count.' '.($count === 1 ? 'foto' : 'foto\'s').' toegevoegd'.($workPackage ? ' bij '.$workPackage->name : ''),
            null,
            $project,
        );

        return back()->with('success', $count.' '.($count === 1 ? 'foto' : 'foto\'s').' geüpload.');
    }

    /**
     * Streamt de foto uit private storage (zelfde patroon als de omslagfoto).
     */
    public function show(Request $request, Photo $photo): BinaryFileResponse
    {
        $magBekijken = $photo->project->isAccessibleBy($request->user())
            || ($photo->client_visible && $photo->project->isViewableByClient($request->user()));

        abort_unless($magBekijken, 403);
        abort_unless(Storage::exists($photo->path), 404);

        return response()->file(Storage::path($photo->path), [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function update(Request $request, Photo $photo): RedirectResponse
    {
        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'client_visible' => ['sometimes', 'boolean'],
        ]);

        $photo->update($validated);

        return back()->with('success', 'Foto bijgewerkt.');
    }

    public function destroy(Request $request, Photo $photo): RedirectResponse
    {
        $magVerwijderen = $request->user()->can('manage-crm')
            || $photo->uploaded_by === $request->user()->id;

        abort_unless($magVerwijderen && $photo->project->isAccessibleBy($request->user()), 403);

        AuditLog::record($photo->project, 'foto_verwijderd', ['caption' => $photo->caption ?? '—'], []);
        Storage::delete($photo->path);
        $photo->delete();

        return back()->with('success', 'Foto verwijderd.');
    }
}
