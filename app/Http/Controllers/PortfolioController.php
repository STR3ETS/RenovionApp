<?php

namespace App\Http\Controllers;

use App\Enums\HealthLight;
use App\Models\Project;
use App\Services\ProjectHealthService;
use App\Support\ProjectHealth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Portfolio (briefing hoofdstuk 6): alle lopende projecten naast elkaar met
 * hun stoplichten, voortgang tegen plan, volgende mijlpaal en de grootste
 * blokkade. Leest de gezondheid in een paar queries, geen query per rij.
 */
class PortfolioController extends Controller
{
    public function __invoke(Request $request, ProjectHealthService $healthService): View
    {
        $request->validate([
            'licht' => ['nullable', Rule::enum(HealthLight::class)],
            'sorteer' => ['nullable', Rule::in(['gezondheid', 'oplevering', 'voortgang', 'waarde'])],
        ]);

        $filter = $request->filled('licht') ? HealthLight::from($request->query('licht')) : null;
        $sort = $request->query('sorteer', 'gezondheid');

        $projects = Project::active()
            ->with([...ProjectHealthService::RELATIONS, 'customer', 'projectLeader'])
            ->get();

        $health = $healthService->forMany($projects);

        $rows = $projects
            ->map(fn (Project $project) => ['project' => $project, 'health' => $health[$project->id]])
            ->when($filter !== null, fn ($rows) => $rows->filter(fn (array $row) => $row['health']->overall === $filter))
            ->sortBy(fn (array $row) => match ($sort) {
                'oplevering' => $row['project']->end_date_expected?->timestamp ?? PHP_INT_MAX,
                'voortgang' => $row['project']->progress,
                'waarde' => -1 * (float) $row['project']->value,
                default => [-1 * $row['health']->overall->severity(), $row['project']->end_date_expected?->timestamp ?? PHP_INT_MAX],
            })
            ->values();

        return view('projects.portfolio', [
            'rows' => $rows,
            'filter' => $filter,
            'sort' => $sort,
            'counts' => collect($health)->countBy(fn (ProjectHealth $item) => $item->overall->value),
        ]);
    }
}
