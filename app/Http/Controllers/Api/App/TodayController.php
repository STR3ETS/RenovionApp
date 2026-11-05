<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\Task;
use App\Support\AppPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Vandaag in de iOS/Android-app: dezelfde gegevens als het Vandaag-scherm van de website. */
class TodayController extends Controller
{
    public function __invoke(Request $request, DashboardController $dashboard): JsonResponse
    {
        $user = $request->user();

        if ($user->cannot('manage-crm')) {
            $data = $dashboard->uitvoerderData($user);

            return response()->json([
                'type' => 'uitvoerder',
                'project' => $data['project'] ? [
                    ...AppPayload::project($data['project'], $user),
                    'chat_unread' => $data['chatOngelezen'],
                    'open_work' => $data['project']->open_werk_count,
                    'photos' => $data['project']->photos_count,
                ] : null,
                'today' => $data['takenVandaag']->values()->map(fn (Task $task) => AppPayload::task($task)),
                'later' => $data['takenLater']->values()->map(fn (Task $task) => AppPayload::task($task)),
                'week' => $data['planningWeek']->map(fn ($entry) => AppPayload::scheduleEntry($entry)),
            ]);
        }

        $data = $dashboard->managementData();

        $first = collect()
            ->concat($data['opvolgLeads']->map(fn ($lead) => [
                'icon' => 'phone',
                'tone' => 'bad',
                'title' => $lead->customer->name.': '.($lead->next_action ?? ($lead->status === LeadStatus::Nieuw ? 'nieuwe aanvraag opvolgen' : 'opvolgen')),
                'sub' => collect([$lead->service ?? 'Aanvraag', $lead->next_action_at?->translatedFormat('j M H:i')])->filter()->implode(', '),
                'action' => $lead->customer->phone
                    ? ['label' => 'Bellen', 'tel' => $lead->customer->phone]
                    : ['label' => 'Bekijk', 'path' => route('leads.show', $lead, false)],
            ]))
            ->concat($data['opvolgOffertes']->map(fn ($quote) => [
                'icon' => 'document-text',
                'tone' => 'or',
                'title' => "Offerte {$quote->number} wacht op opvolging",
                'sub' => $quote->customer->name.', al '.$quote->daysOpen().' dagen open',
                'action' => ['label' => 'Bekijk', 'path' => route('quotes.show', $quote, false)],
            ]))
            ->concat($data['betaalRisicos']->map(fn ($project) => [
                'icon' => 'currency-euro',
                'tone' => 'bad',
                'title' => "Aanbetaling {$project->customer->name} controleren",
                'sub' => '€ '.number_format((float) $project->deposit_amount, 0, ',', '.').', start '.$project->start_date->translatedFormat('j M'),
                'action' => ['label' => 'Controleer', 'path' => route('projects.show', $project, false)],
            ]));

        return response()->json([
            'type' => 'kantoor',
            'attention_count' => $data['attentionCount'],
            'stats' => $data['stats'],
            'first' => $first->values(),
            'planning' => $data['planningVandaag']->map(fn ($entry) => AppPayload::scheduleEntry($entry)),
            'tasks' => $data['taken']->map(fn (Task $task) => AppPayload::task($task)),
            'projects' => $data['recenteProjecten']->map(fn ($project) => AppPayload::project($project, $user)),
        ]);
    }
}
