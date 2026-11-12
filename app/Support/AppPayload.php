<?php

namespace App\Support;

use App\Enums\ActionSource;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Models\TimelineEvent;
use App\Models\User;

/**
 * Gegevens voor de schermen die in de iOS/Android-app zelf zitten (mobile/app). De app krijgt alleen
 * data; hoe het eruitziet staat in de app. Paden zijn relatief en wijzen naar de serverpagina's.
 */
class AppPayload
{
    /** @return array<string, mixed> */
    public static function project(Project $project, User $viewer): array
    {
        $phase = $project->relationLoaded('phases') ? $project->currentPhase() : null;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'customer' => $project->customer->name,
            'city' => $project->city,
            'status' => ['label' => $project->status->label(), 'classes' => $project->status->appBadgeClasses()],
            'phase' => $phase?->name,
            'overdue' => $project->isOverdue(),
            'progress' => $project->progress,
            'value' => $viewer->can('manage-crm') ? (float) $project->value : null,
            'leader' => $project->relationLoaded('projectLeader') ? $project->projectLeader?->name : null,
            'path' => route('projects.show', $project, false),
        ];
    }

    /** @return array<string, mixed> */
    public static function task(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'context' => $task->customer?->name ?? $task->project?->name,
            'deadline' => $task->deadline?->toDateString(),
            'overdue' => $task->isOverdue(),
        ];
    }

    /** Een regel uit de klanttimeline (klantdossier en aanvraag), zoals op customers/show. @return array<string, mixed> */
    public static function timelineEvent(TimelineEvent $event): array
    {
        $viaNova = in_array($event->source, [ActionSource::Nova, ActionSource::Voice], true);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'body' => $event->body,
            'icon' => $event->type->icon(),
            'type' => $event->type->label(),
            'nova' => $viaNova,
            'who' => $viaNova
                ? 'Nova'.($event->user ? ', bevestigd door '.$event->user->name : '')
                : ($event->user?->name ?? $event->source->label()),
            'at' => $event->happened_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function scheduleEntry(ScheduleEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'date' => $entry->date->toDateString(),
            'start' => $entry->start_time ? substr($entry->start_time, 0, 5) : null,
            'end' => $entry->end_time ? substr($entry->end_time, 0, 5) : null,
            'title' => $entry->displayTitle(),
            'who' => $entry->user?->name,
            'type' => $entry->type->label(),
            'path' => $entry->project ? route('projects.show', $entry->project, false) : route('planning.index', [], false),
        ];
    }
}
