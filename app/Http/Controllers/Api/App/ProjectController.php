<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\AppPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Projectdossiers in de iOS/Android-app: dezelfde selectie als de projectenlijst van de website. */
class ProjectController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
        ]);

        $user = $request->user();
        $status = $request->filled('status') ? ProjectStatus::from($request->query('status')) : null;

        $projects = Project::with(['customer', 'projectLeader', 'phases'])
            ->when($user->cannot('manage-crm'), fn ($query) => $query->whereHas(
                'craftsmen', fn ($craftsmen) => $craftsmen->where('users.id', $user->id)
            ))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($status === null, fn ($query) => $query->active())
            ->orderByRaw('start_date is null, start_date asc')
            ->limit(100)
            ->get();

        return response()->json([
            'filters' => [
                ['value' => null, 'label' => 'Lopend'],
                ...array_map(fn (ProjectStatus $option) => ['value' => $option->value, 'label' => $option->label()], ProjectStatus::cases()),
            ],
            'projects' => $projects->map(fn (Project $project) => AppPayload::project($project, $user)),
        ]);
    }
}
