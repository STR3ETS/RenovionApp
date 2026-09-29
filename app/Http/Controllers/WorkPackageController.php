<?php

namespace App\Http\Controllers;

use App\Enums\PhaseStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkPackageController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'project_phase_id' => ['required', Rule::exists('project_phases', 'id')->where('project_id', $project->id)],
            'name' => ['required', 'string', 'max:255'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'deadline' => ['nullable', 'date'],
        ]);

        $package = $project->workPackages()->create([
            ...$validated,
            'status' => PhaseStatus::NietGestart,
            'position' => ((int) $project->workPackages()->where('project_phase_id', $validated['project_phase_id'])->max('position')) + 1,
        ]);

        AuditLog::record($package, 'aangemaakt', [], ['name' => $package->name]);
        $project->syncProgress();

        return redirect()
            ->route('work-packages.show', $package)
            ->with('success', 'Werkpakket aangemaakt — voeg de checklist toe.');
    }

    public function show(Request $request, WorkPackage $workPackage): View
    {
        abort_unless($workPackage->project->isAccessibleBy($request->user()), 403);

        $workPackage->load(['project.customer', 'phase', 'responsible', 'items.doneBy']);

        return view('work-packages.show', [
            'workPackage' => $workPackage,
            'team' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, WorkPackage $workPackage): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'deadline' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::enum(PhaseStatus::class)],
        ]);

        // Gereed melden loopt via de afrondknop, zodat de checklist-check geldt.
        if (($validated['status'] ?? null) === PhaseStatus::Gereed->value && ! $workPackage->isDone()) {
            return $this->complete($request, $workPackage);
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== PhaseStatus::Gereed->value) {
            $validated['completed_at'] = null;
        }

        $workPackage->update($validated);

        if ($workPackage->wasChanged()) {
            AuditLog::record($workPackage, 'bijgewerkt', [], $workPackage->getChanges());
        }

        $workPackage->project->syncProgress();

        return redirect()->route('work-packages.show', $workPackage)->with('success', 'Werkpakket bijgewerkt.');
    }

    /**
     * Werkpakket afronden (mockup "Taak afronden"): kan pas als de checklist
     * compleet is. Ook uitvoerders op het project mogen dit.
     */
    public function complete(Request $request, WorkPackage $workPackage): RedirectResponse
    {
        abort_unless($workPackage->project->isAccessibleBy($request->user()), 403);

        $workPackage->load('items');

        if (! $workPackage->checklistComplete()) {
            return redirect()->route('work-packages.show', $workPackage)
                ->with('error', 'Nog niet alle checklistitems zijn afgevinkt — rond eerst de checklist af.');
        }

        $workPackage->update([
            'status' => PhaseStatus::Gereed,
            'completed_at' => now(),
        ]);

        AuditLog::record($workPackage, 'afgerond', [], ['name' => $workPackage->name]);
        $workPackage->project->customer->recordEvent(
            TimelineEventType::Projectupdate,
            'Werkpakket afgerond: '.$workPackage->name,
            null,
            $workPackage,
        );

        $workPackage->project->syncProgress();

        return redirect()->route('work-packages.show', $workPackage)
            ->with('success', 'Werkpakket afgerond.');
    }

    /**
     * Heropenen na (per ongeluk) afronden.
     */
    public function reopen(Request $request, WorkPackage $workPackage): RedirectResponse
    {
        abort_unless($workPackage->project->isAccessibleBy($request->user()), 403);

        $workPackage->update([
            'status' => PhaseStatus::Bezig,
            'completed_at' => null,
        ]);

        AuditLog::record($workPackage, 'heropend', [], ['name' => $workPackage->name]);
        $workPackage->project->syncProgress();

        return redirect()->route('work-packages.show', $workPackage)->with('success', 'Werkpakket heropend.');
    }

    public function destroy(WorkPackage $workPackage): RedirectResponse
    {
        $project = $workPackage->project;

        AuditLog::record($workPackage, 'verwijderd', ['name' => $workPackage->name], []);
        $workPackage->delete();
        $project->syncProgress();

        return redirect()->route('projects.show', $project)->with('success', 'Werkpakket verwijderd.');
    }
}
