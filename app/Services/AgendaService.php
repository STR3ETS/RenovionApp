<?php

namespace App\Services;

use App\Enums\ScheduleEntryType;
use App\Models\Lead;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * De agenda per medewerker. Vier bronnen komen samen op één raster: afspraken,
 * taken met een tijd, belacties uit de leadopvolging en werk uit de planning.
 *
 * Elke bron is één query voor alle gevraagde mensen en dagen samen, zodat de
 * teamweergave niet per persoon opnieuw de database in gaat.
 */
class AgendaService
{
    /** Het raster loopt van zeven tot zeven. */
    public const FIRST_HOUR = 7;

    public const LAST_HOUR = 18;

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array<string, mixed>>
     */
    public function items(Collection $users, Carbon $from, Carbon $until): Collection
    {
        $ids = $users->pluck('id')->all();

        return collect()
            ->concat($this->entries($ids, $from, $until))
            ->concat($this->tasks($ids, $from, $until))
            ->concat($this->calls($ids, $from, $until))
            ->sortBy(fn (array $item) => $item['date'].' '.($item['start'] ?? '00:00'))
            ->values();
    }

    /**
     * Afspraken en planningsblokken. Een blok van meerdere dagen staat op elke
     * werkdag die het beslaat; een afspraak staat bij de eigenaar en bij iedere
     * deelnemer.
     *
     * @param  list<int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    private function entries(array $ids, Carbon $from, Carbon $until): Collection
    {
        $entries = ScheduleEntry::query()
            ->where(fn ($query) => $query
                ->whereIn('user_id', $ids)
                ->orWhereHas('participants', fn ($participants) => $participants->whereIn('users.id', $ids)))
            ->where(fn ($query) => $query
                ->where(fn ($day) => $day->whereDate('date', '>=', $from)->whereDate('date', '<=', $until))
                ->orWhere(fn ($range) => $range
                    ->whereDate('date', '<=', $until)
                    ->whereDate('end_date', '>=', $from)))
            ->with(['project', 'customer', 'lead.customer', 'participants:id,name'])
            ->get();

        $items = collect();

        foreach ($entries as $entry) {
            $people = collect([$entry->user_id])
                ->merge($entry->participants->pluck('id'))
                ->intersect($ids)
                ->unique();

            $isWork = $entry->type === ScheduleEntryType::Project;
            $last = $entry->end_date ?? $entry->date;

            for ($day = $entry->date->copy(); $day->lessThanOrEqualTo($last); $day->addDay()) {
                if ($day->lessThan($from) || $day->greaterThan($until) || ($isWork && $day->isWeekend())) {
                    continue;
                }

                foreach ($people as $userId) {
                    $items->push([
                        'kind' => $isWork ? 'werk' : 'afspraak',
                        'label' => $entry->type->label(),
                        'user_id' => $userId,
                        'date' => $day->toDateString(),
                        'start' => $entry->start_time ? substr($entry->start_time, 0, 5) : null,
                        'end' => $entry->end_time ? substr($entry->end_time, 0, 5) : null,
                        'title' => $entry->displayTitle(),
                        'sub' => collect([
                            $entry->customer?->name ?? $entry->lead?->customer?->name,
                            $entry->participants->isNotEmpty() ? 'met '.$entry->participants->pluck('name')->join(', ') : null,
                        ])->filter()->join(', '),
                        'url' => $isWork && $entry->project_id ? route('projects.show', $entry->project_id) : null,
                        'done' => $entry->done_at !== null,
                        'late' => false,
                        'entry_id' => $entry->id,
                    ]);
                }
            }
        }

        return $items;
    }

    /**
     * Open taken met een deadline in deze periode. Met een tijd staan ze op het
     * raster, zonder tijd bovenaan de dag.
     *
     * @param  list<int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    private function tasks(array $ids, Carbon $from, Carbon $until): Collection
    {
        return Task::open()
            ->whereIn('owner_id', $ids)
            ->whereDate('deadline', '>=', $from)
            ->whereDate('deadline', '<=', $until)
            ->with(['project:id,name', 'customer:id,name'])
            ->get()
            ->map(fn (Task $task) => [
                'kind' => 'taak',
                'label' => 'Taak',
                'user_id' => $task->owner_id,
                'date' => $task->deadline->toDateString(),
                'start' => $task->due_time ? substr($task->due_time, 0, 5) : null,
                'end' => null,
                'title' => $task->title,
                'sub' => $task->project?->name ?? $task->customer?->name ?? '',
                'url' => route('tasks.index'),
                'done' => false,
                'late' => $task->deadline->isPast() && ! $task->deadline->isToday(),
                'task_id' => $task->id,
            ]);
    }

    /**
     * De volgende stap op een open aanvraag: bellen, terugbellen, mailen of
     * een offerte opvolgen. Een aanvraag zonder eigenaar staat bij niemand in
     * de agenda en komt daarom in de teamweergave op een eigen rij.
     *
     * @param  list<int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    private function calls(array $ids, Carbon $from, Carbon $until): Collection
    {
        return Lead::open()
            ->whereNotNull('next_action_at')
            ->whereBetween('next_action_at', [$from->copy()->startOfDay(), $until->copy()->endOfDay()])
            ->where(fn ($query) => $query->whereIn('assigned_to', $ids)->orWhereNull('assigned_to'))
            ->with('customer')
            ->get()
            ->map(fn (Lead $lead) => [
                'kind' => 'bellen',
                'label' => $lead->next_action_type?->label() ?? ($lead->next_action ?: 'Opvolgen'),
                'user_id' => $lead->assigned_to,
                'date' => $lead->next_action_at->toDateString(),
                'start' => $lead->next_action_at->format('H:i') === '00:00' ? null : $lead->next_action_at->format('H:i'),
                'end' => null,
                'title' => $lead->customer->name,
                'sub' => collect([
                    $lead->customer->phone,
                    $lead->missed_calls > 0 ? $lead->missed_calls.' keer geen gehoor' : null,
                ])->filter()->join(', '),
                'url' => route('leads.show', $lead),
                'done' => false,
                'late' => $lead->next_action_at->isPast(),
                'lead_id' => $lead->id,
                'advice' => $lead->callAdvice(),
            ]);
    }
}
