<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\HealthLight;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PortfolioController as WebPortfolioController;
use App\Services\ProjectHealthService;
use App\Support\HealthSignal;
use App\Support\ProjectHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portfolio in de app (projects/portfolio): alle lopende projecten met hun vier stoplichten, voortgang
 * tegen plan, volgende mijlpaal en grootste blokkade, met tellers per licht als filter en sortering.
 * Filteren en sorteren deelt hij met de website (PortfolioController::portfolio).
 */
class PortfolioController extends Controller
{
    public const SORTS = ['gezondheid' => 'Gezondheid', 'oplevering' => 'Opleverdatum', 'voortgang' => 'Voortgang', 'waarde' => 'Waarde'];

    public function __invoke(Request $request, ProjectHealthService $healthService): JsonResponse
    {
        ['rows' => $rows, 'filter' => $filter, 'sort' => $sort, 'counts' => $counts] = WebPortfolioController::portfolio($request, $healthService);

        return response()->json([
            'filter' => $filter?->value,
            'sort' => $sort,
            'sorts' => collect(self::SORTS)->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label])->values(),
            'counts' => array_map(fn (HealthLight $light) => [
                'value' => $light->value,
                'label' => $light->label(),
                'dot' => $light->dotColor(),
                'count' => (int) ($counts[$light->value] ?? 0),
            ], [HealthLight::Rood, HealthLight::Oranje, HealthLight::Groen, HealthLight::Grijs]),
            'projects' => $rows->map(function (array $row) {
                $project = $row['project'];
                /** @var ProjectHealth $health */
                $health = $row['health'];
                $variance = $health->scheduleVariance();

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'path' => route('projects.show', $project, false),
                    'sub' => collect([
                        $project->customer?->name,
                        $project->city,
                        $project->projectLeader?->name,
                    ])->filter()->implode(', '),
                    'overall' => [
                        'value' => $health->overall->value,
                        'label' => $health->overall->label(),
                        'dot' => $health->overall->dotColor(),
                        'classes' => $health->overall->appBadgeClasses(),
                    ],
                    'signals' => array_values(array_map(fn (HealthSignal $signal) => [
                        'key' => $signal->key,
                        'label' => $signal->label,
                        'initial' => mb_substr($signal->label, 0, 1),
                        'dot' => $signal->light->dotColor(),
                        'reason' => $signal->reason,
                    ], $health->signals)),
                    'progress' => $health->progress,
                    'expected' => $health->expectedProgress,
                    // Zelfde kleur en tekst als <x-progress-vs-plan> op de website.
                    'bar' => match (true) {
                        $variance === null => 'brand',
                        $variance >= 10 => 'red',
                        $variance >= 5 => 'amber',
                        default => 'green',
                    },
                    'plan' => match (true) {
                        $variance === null => 'Geen start- of opleverdatum ingevuld, dus geen vergelijking met de planning.',
                        $variance > 0 => "{$variance}% achter op planning, zou op {$health->expectedProgress}% moeten staan.",
                        $variance < 0 => abs($variance).'% voor op planning.',
                        default => 'Precies op planning.',
                    },
                    'milestone' => $health->nextMilestone ?? 'Alles gereed',
                    'milestone_due' => $health->nextMilestoneDue ?? 'geen datum gepland',
                    'blocker' => $health->biggestBlocker,
                ];
            })->values(),
            'links' => ['projects' => route('projects.index', [], false)],
        ]);
    }
}
