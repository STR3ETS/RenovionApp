<?php

namespace App\Http\Controllers\Api\App;

use App\Actions\CompleteWorkPackage;
use App\Actions\StorePhotos;
use App\Actions\ToggleChecklistItem;
use App\Http\Controllers\Controller;
use App\Models\ChecklistItem;
use App\Models\Photo;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Werkpakket in de iOS/Android-app: checklist afvinken, foto-bewijs maken en afronden, ook voor
 * uitvoerders op hun eigen projecten. Elke wijziging geeft het hele werkpakket terug, zodat het scherm
 * meteen klopt. Beheer (naam, verantwoordelijke, checklist aanpassen) opent nog als serverpagina.
 */
class WorkPackageController extends Controller
{
    public function show(Request $request, WorkPackage $workPackage): JsonResponse
    {
        $this->authorizeAccess($request, $workPackage);

        return response()->json($this->payload($workPackage, $request->user()));
    }

    public function toggle(Request $request, WorkPackage $workPackage, ChecklistItem $item, ToggleChecklistItem $toggle): JsonResponse
    {
        $this->authorizeAccess($request, $workPackage);
        abort_if($workPackage->isDone(), 422, 'Dit werkpakket is al afgerond. Heropen het eerst.');

        $toggle->handle($item, $request->user());

        return response()->json($this->payload($workPackage, $request->user()));
    }

    public function photos(Request $request, WorkPackage $workPackage, StorePhotos $storePhotos): JsonResponse
    {
        $this->authorizeAccess($request, $workPackage);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:20'],
            'photos.*' => ['image', 'max:10240'],
            'checklist_item_id' => ['nullable', 'integer'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $storePhotos->handle($workPackage->project, $validated['photos'], $request->user(), $workPackage, $validated);

        return response()->json($this->payload($workPackage, $request->user()), 201);
    }

    public function complete(Request $request, WorkPackage $workPackage, CompleteWorkPackage $completeWorkPackage): JsonResponse
    {
        $this->authorizeAccess($request, $workPackage);

        $completeWorkPackage->handle($workPackage);

        return response()->json($this->payload($workPackage, $request->user()));
    }

    public function reopen(Request $request, WorkPackage $workPackage, CompleteWorkPackage $completeWorkPackage): JsonResponse
    {
        $this->authorizeAccess($request, $workPackage);

        $completeWorkPackage->reopen($workPackage);

        return response()->json($this->payload($workPackage, $request->user()));
    }

    public function destroyPhoto(Request $request, Photo $photo): JsonResponse
    {
        $user = $request->user();
        abort_unless(($user->can('manage-crm') || $photo->uploaded_by === $user->id) && $photo->project->isAccessibleBy($user), 403);
        abort_if($photo->workPackage?->isDone(), 422, 'Dit werkpakket is al afgerond. Heropen het eerst.');

        $workPackage = $photo->workPackage;
        Storage::delete($photo->path);
        $photo->delete();

        return response()->json($workPackage ? $this->payload($workPackage, $user) : null);
    }

    /**
     * Een foto voor <img> in de app: die kan geen token meesturen, dus een ondertekend adres. Het adres
     * blijft de hele dag hetzelfde, zodat de bewaarde gegevens in de app niet elke keer anders zijn.
     */
    public function photo(Photo $photo): BinaryFileResponse
    {
        abort_unless(Storage::exists($photo->path), 404);

        return response()->file(Storage::path($photo->path), ['Cache-Control' => 'private, max-age=86400']);
    }

    private function authorizeAccess(Request $request, WorkPackage $workPackage): void
    {
        abort_unless($workPackage->project->isAccessibleBy($request->user()), 403);
    }

    /** @return array<string, mixed> */
    private function payload(WorkPackage $workPackage, User $user): array
    {
        $workPackage->refresh()->load(['project.customer', 'phase', 'responsible', 'items.doneBy', 'items.photos', 'photos.uploader', 'photos.checklistItem']);
        $crm = $user->can('manage-crm');
        $expires = today()->addDays(2);

        return [
            'id' => $workPackage->id,
            'name' => $workPackage->name,
            'description' => $workPackage->description,
            'status' => ['value' => $workPackage->status->value, 'label' => $workPackage->status->label(), 'classes' => $workPackage->status->appBadgeClasses()],
            'done' => $workPackage->isDone(),
            'completed_at' => $workPackage->completed_at?->translatedFormat('j M Y H:i'),
            'deadline' => $workPackage->deadline?->toDateString(),
            'overdue' => $workPackage->isOverdue(),
            'responsible' => $workPackage->responsible?->name,
            'phase' => $workPackage->phase->name,
            'project' => ['id' => $workPackage->project->id, 'name' => $workPackage->project->name, 'customer' => $workPackage->project->customer->name],
            'checklist_complete' => $workPackage->checklistComplete(),
            'evidence_complete' => $workPackage->evidenceComplete(),
            'items' => $workPackage->items->map(fn (ChecklistItem $item) => [
                'id' => $item->id,
                'label' => $item->label,
                'done' => $item->isDone(),
                'done_by' => $item->isDone() ? collect([$item->doneBy?->name, $item->done_at->translatedFormat('j M H:i')])->filter()->implode(', ') : null,
                'requires_photos' => $item->requires_photos,
                'photos' => $item->photos->count(),
            ])->values(),
            'photos' => $workPackage->photos->map(fn (Photo $photo) => [
                'id' => $photo->id,
                'url' => URL::temporarySignedRoute('api.app.photo', $expires, $photo, absolute: false),
                'caption' => $photo->caption ?? $photo->checklistItem?->label ?? 'Bewijsfoto',
                'meta' => collect([$photo->created_at->translatedFormat('j M H:i'), $photo->uploader?->name])->filter()->implode(', '),
                'internal' => ! $photo->client_visible,
                'removable' => $crm || $photo->uploaded_by === $user->id,
            ])->values(),
            'path' => route('work-packages.show', $workPackage, false),
            'can_manage' => $crm,
        ];
    }
}
