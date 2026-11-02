<?php

namespace App\Http\Controllers;

use App\Enums\PhaseStatus;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het klantportaal zoals de klant het ziet, maar van binnenuit te openen.
 *
 * Dat is nodig voordat je iets vrijgeeft: je wil kunnen zien welke foto's en
 * welke voortgang bij de klant op het scherm staan, zonder in te loggen met een
 * klantaccount.
 */
class PortalPreviewController extends Controller
{
    public function index(Request $request): View
    {
        $projects = Project::active()
            ->with(['customer.portalUser', 'phases', 'photos'])
            ->withCount([
                'photos as client_photos_count' => fn ($query) => $query->where('client_visible', true),
                'photos as hidden_photos_count' => fn ($query) => $query->where('client_visible', false),
            ])
            ->orderBy('name')
            ->get();

        return view('portal-preview.index', [
            'rows' => $projects->map(fn (Project $project) => [
                'project' => $project,
                // Fasen die op de klant wachten: dat is wat de klant als eerste ziet.
                'waiting' => $project->phases->where('status', PhaseStatus::WachtOpKlant),
                'hasAccount' => $project->customer?->portalUser !== null,
            ]),
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        abort_unless($project->isAccessibleBy($request->user()), 403);

        $project->load([
            'customer.portalUser',
            'phases.approver',
            'photos' => fn ($query) => $query->where('client_visible', true),
            'scheduleEntries.user',
            'serviceTickets',
        ]);

        return view('portal-preview.show', [
            'project' => $project,
            'waiting' => $project->phases->where('status', PhaseStatus::WachtOpKlant),
            'approved' => $project->phases->whereNotNull('client_approved_at'),
            'hidden' => $project->photos()->where('client_visible', false)->count(),
            'hasAccount' => $project->customer?->portalUser !== null,
        ]);
    }
}
