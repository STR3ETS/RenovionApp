<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Task;
use App\Support\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Zoeken in de app: dezelfde resultaten als de zoekpagina van de website (GlobalSearch). */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $results = GlobalSearch::run((string) $request->query('q'));

        return response()->json([
            'projects' => $results['projects']->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'sub' => collect([$project->customer->name, $project->city])->filter()->implode(', '),
                'status' => ['label' => $project->status->label(), 'classes' => $project->status->appBadgeClasses()],
                'path' => route('projects.show', $project, false),
            ]),
            'customers' => $results['customers']->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'sub' => collect([$customer->city, $customer->phone, $customer->email])->filter()->implode(', ') ?: null,
                'path' => route('customers.show', $customer, false),
            ]),
            'leads' => $results['leads']->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'name' => $lead->customer->name,
                'sub' => $lead->service ?? 'Aanvraag',
                'status' => ['label' => $lead->status->label(), 'classes' => $lead->status->appBadgeClasses()],
                'path' => route('leads.show', $lead, false),
            ]),
            'tasks' => $results['tasks']->map(fn (Task $task) => [
                'id' => $task->id,
                'name' => $task->title,
                'sub' => collect([$task->project?->name ?? $task->customer?->name ?? 'Algemeen', $task->deadline?->translatedFormat('j M')])->filter()->implode(', '),
                'status' => ['label' => $task->status->label(), 'classes' => $task->status->appBadgeClasses()],
            ]),
        ]);
    }
}
