<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ChecklistItem;
use App\Models\WorkPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChecklistItemController extends Controller
{
    public function store(Request $request, WorkPackage $workPackage): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'requires_photos' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $workPackage->items()->create([
            'label' => $validated['label'],
            'requires_photos' => $validated['requires_photos'] ?? 0,
            'position' => ((int) $workPackage->items()->max('position')) + 1,
        ]);

        return redirect()->route('work-packages.show', $workPackage)->with('success', 'Checklistitem toegevoegd.');
    }

    /**
     * Afvinken / weer openzetten — ook voor uitvoerders op het project (mobile-first).
     */
    public function toggle(Request $request, WorkPackage $workPackage, ChecklistItem $item): RedirectResponse
    {
        abort_unless($workPackage->project->isAccessibleBy($request->user()), 403);

        $item->update($item->isDone()
            ? ['done_at' => null, 'done_by' => null]
            : ['done_at' => now(), 'done_by' => $request->user()->id]);

        AuditLog::record($item, $item->isDone() ? 'afgevinkt' : 'heropend', [], ['label' => $item->label]);

        return redirect()->route('work-packages.show', $workPackage);
    }

    public function destroy(WorkPackage $workPackage, ChecklistItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route('work-packages.show', $workPackage)->with('success', 'Checklistitem verwijderd.');
    }
}
