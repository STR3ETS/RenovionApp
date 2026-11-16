<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Foto's in de app (mobile/app/screens/fotos.*): de Foto's-tab van een project (projects/tabs/fotos: filter
 * per fase, uploaden met fase, omschrijving en klantzichtbaarheid, verwijderen) en één foto schermvullend
 * (photos.show op de website). Uploaden, bijwerken en verwijderen gaan via dezelfde regels als de website:
 * PhotoController@store/update geven JSON aan de app, verwijderen gaat via api.app.photos.destroy.
 * <img> kan geen token meesturen: elke foto komt via het ondertekende adres api.app.photo.
 */
class PhotoController extends Controller
{
    public function project(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        abort_unless($project->isAccessibleBy($user), 403);

        $project->load('phases');
        $photos = $project->photos()->with(['uploader', 'workPackage'])->get();

        return response()->json([
            'project' => ['id' => $project->id, 'name' => $project->name, 'path' => route('projects.show', $project, false)],
            'total' => $photos->count(),
            // Filter per fase: alleen fasen met foto's, zoals op de website.
            'phases' => $project->phases
                ->map(fn (ProjectPhase $phase) => ['id' => $phase->id, 'name' => $phase->name, 'count' => $photos->where('project_phase_id', $phase->id)->count()])
                ->filter(fn (array $phase) => $phase['count'] > 0)
                ->values(),
            // Uploaden: elke fase kiesbaar, de huidige fase staat klaar.
            'upload_phases' => $project->phases->map(fn (ProjectPhase $phase) => ['id' => $phase->id, 'name' => $phase->name])->values(),
            'current_phase_id' => $project->currentPhase()?->id,
            'photos' => $photos->map(fn (Photo $photo) => [
                'id' => $photo->id,
                'url' => $this->url($photo),
                'caption' => $photo->caption ?? $photo->workPackage?->name ?? 'Projectfoto',
                'meta' => $photo->created_at->translatedFormat('j M').($photo->uploader ? ', '.$photo->uploader->name : ''),
                'phase_id' => $photo->project_phase_id,
                'internal' => ! $photo->client_visible,
                'removable' => $this->removable($photo, $user),
            ])->values(),
        ]);
    }

    /** Eén foto: dezelfde toegang als photos.show op de website. */
    public function show(Request $request, Photo $photo): JsonResponse
    {
        $user = $request->user();
        $photo->load(['project', 'phase', 'workPackage', 'checklistItem', 'uploader']);
        abort_unless($photo->project->isAccessibleBy($user) || ($photo->client_visible && $photo->project->isViewableByClient($user)), 403);

        $ids = $photo->project->photos()->pluck('id');
        $position = $ids->search($photo->id);

        return response()->json([
            'id' => $photo->id,
            'url' => $this->url($photo),
            'caption' => $photo->caption ?? $photo->checklistItem?->label ?? $photo->workPackage?->name ?? 'Projectfoto',
            'raw_caption' => $photo->caption,
            'meta' => collect([$photo->created_at->translatedFormat('j M Y H:i'), $photo->uploader?->name])->filter()->implode(', '),
            'phase' => $photo->phase?->name,
            'work_package' => $photo->workPackage ? ['name' => $photo->workPackage->name, 'path' => route('work-packages.show', $photo->workPackage, false)] : null,
            'checklist_item' => $photo->checklistItem?->label,
            'project' => ['id' => $photo->project->id, 'name' => $photo->project->name, 'path' => route('projects.show', $photo->project, false)],
            'internal' => ! $photo->client_visible,
            'removable' => $this->removable($photo, $user),
            'editable' => $user->can('manage-crm'),
            'position' => $position === false ? null : $position + 1,
            'count' => $ids->count(),
            'previous' => $position ? $ids[$position - 1] : null,
            'next' => $position !== false && $position + 1 < $ids->count() ? $ids[$position + 1] : null,
        ]);
    }

    /** Het adres blijft de hele dag gelijk, zodat de bewaarde gegevens in de app niet elke keer anders zijn. */
    private function url(Photo $photo): string
    {
        return URL::temporarySignedRoute('api.app.photo', today()->addDays(2), $photo, absolute: false);
    }

    private function removable(Photo $photo, User $user): bool
    {
        return $user->can('manage-crm') || $photo->uploaded_by === $user->id;
    }
}
