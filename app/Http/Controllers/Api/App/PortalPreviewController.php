<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\PhaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\ScheduleEntry;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Het klantportaal zoals de klant het ziet, in de app (portal-preview/index en show): lopende projecten
 * met portaalaccount, wachtende fasen en zichtbare foto's, en per project de voortgang, wat op de klant
 * wacht, de foto's die hij ziet, wat hij al aftekende, deze week en service. Alleen lezen.
 */
class PortalPreviewController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = Project::active()
            ->with(['customer.portalUser', 'phases'])
            ->withCount([
                'photos as client_photos_count' => fn ($query) => $query->where('client_visible', true),
                'photos as hidden_photos_count' => fn ($query) => $query->where('client_visible', false),
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'has_account' => $project->customer?->portalUser !== null,
                'waiting' => $project->phases->where('status', PhaseStatus::WachtOpKlant)->isNotEmpty(),
                'sub' => collect([
                    $project->customer?->name,
                    'voortgang '.$project->progress.'%',
                    $project->client_photos_count.' '.($project->client_photos_count === 1 ? 'foto' : "foto's").' zichtbaar',
                    $project->hidden_photos_count > 0 ? $project->hidden_photos_count.' verborgen' : null,
                ])->filter()->implode(', '),
                'path' => route('portal-preview.show', $project, false),
            ])->values(),
        ]);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->isAccessibleBy($request->user()), 403);

        $project->load([
            'customer.portalUser',
            'phases.approver',
            'photos' => fn ($query) => $query->where('client_visible', true),
            'scheduleEntries.user',
            'serviceTickets',
        ]);

        $expires = today()->addDays(2);
        // client_approved_by is de gebruiker (klantaccount) die aftekende: zijn naam, niet het nummer.
        $approvers = User::whereIn('id', $project->phases->pluck('client_approved_by')->filter()->unique())->pluck('name', 'id');
        $expected = $project->end_date_expected;
        $late = $expected?->isPast() && $project->end_date_actual === null;

        return response()->json([
            'id' => $project->id,
            'name' => $project->name,
            'customer' => $project->customer?->name,
            'has_account' => $project->customer?->portalUser !== null,
            'progress' => (int) $project->progress,
            'delivery' => [
                'late' => $late,
                'text' => match (true) {
                    $project->end_date_actual !== null => 'Opgeleverd op '.$project->end_date_actual->translatedFormat('j F Y').'.',
                    (bool) $expected?->isPast() => 'De verwachte opleverdatum van '.$expected->translatedFormat('j F').' is verstreken. Dat ziet de klant ook.',
                    $expected !== null => 'Verwachte oplevering '.$expected->translatedFormat('j F Y').', op schema.',
                    default => 'Nog geen verwachte opleverdatum vastgelegd.',
                },
            ],
            'waiting' => $project->phases->where('status', PhaseStatus::WachtOpKlant)->values()->map(fn (ProjectPhase $phase) => ['id' => $phase->id, 'name' => $phase->name]),
            'photos' => [
                'visible' => $project->photos->count(),
                'hidden' => $project->photos()->where('client_visible', false)->count(),
                'items' => $project->photos->take(12)->values()->map(fn (Photo $photo) => [
                    'id' => $photo->id,
                    'url' => URL::temporarySignedRoute('api.app.photo', $expires, $photo, absolute: false),
                    'caption' => $photo->caption,
                ]),
            ],
            'approved' => $project->phases->whereNotNull('client_approved_at')->values()->map(fn (ProjectPhase $phase) => [
                'id' => $phase->id,
                'name' => $phase->name,
                'sub' => collect([$approvers[$phase->client_approved_by] ?? null, $phase->client_approved_at->translatedFormat('j M Y')])->filter()->implode(', '),
            ]),
            'week' => $project->scheduleEntries
                ->filter(fn (ScheduleEntry $entry) => $entry->date->betweenIncluded(today(), today()->addDays(7)))
                ->sortBy('date')
                ->values()
                ->map(fn (ScheduleEntry $entry) => [
                    'id' => $entry->id,
                    'title' => $entry->displayTitle(),
                    'sub' => collect([$entry->date->translatedFormat('l j M'), $entry->user?->name])->filter()->implode(', '),
                ]),
            'tickets' => $project->serviceTickets->take(5)->values()->map(fn (ServiceTicket $ticket) => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'status' => ['label' => $ticket->statusLabel(), 'classes' => $ticket->appBadgeClasses()],
            ]),
            'project' => ['name' => $project->name, 'path' => route('projects.show', $project, false)],
            'links' => [
                'dossier' => route('projects.show', $project, false),
            ],
        ]);
    }
}
