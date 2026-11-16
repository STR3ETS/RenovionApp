<?php

namespace App\Http\Controllers;

use App\Actions\ToggleChecklistItem;
use App\Models\ChecklistItem;
use App\Models\WorkPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChecklistItemController extends Controller
{
    public function store(Request $request, WorkPackage $workPackage): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'requires_photos' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $item = $workPackage->items()->create([
            'label' => $validated['label'],
            'requires_photos' => $validated['requires_photos'] ?? 0,
            'position' => ((int) $workPackage->items()->max('position')) + 1,
        ]);

        // De app (werkpakketscherm) ververst zelf.
        if ($request->expectsJson()) {
            return response()->json(['id' => $item->id, 'message' => 'Checklistitem toegevoegd.'], 201);
        }

        return redirect()->route('work-packages.show', $workPackage)->with('success', 'Checklistitem toegevoegd.');
    }

    /**
     * Afvinken / weer openzetten - ook voor uitvoerders op het project (mobile-first).
     */
    public function toggle(Request $request, WorkPackage $workPackage, ChecklistItem $item, ToggleChecklistItem $toggle): RedirectResponse
    {
        abort_unless($workPackage->project->isAccessibleBy($request->user()), 403);

        try {
            $toggle->handle($item, $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('work-packages.show', $workPackage)->with('error', $exception->getMessage());
        }

        return redirect()->route('work-packages.show', $workPackage);
    }

    public function destroy(WorkPackage $workPackage, ChecklistItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route('work-packages.show', $workPackage)->with('success', 'Checklistitem verwijderd.');
    }
}
