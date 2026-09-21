<?php

namespace App\Services;

use App\Enums\ScheduleEntryType;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use App\Models\ScheduleEntry;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * De Aandacht-module (briefing §16): alleen uitzonderingen, geen lijst met
 * vijftig dingen die goed gaan. Elk item is een beslissing of actie waard.
 */
class AttentionService
{
    /**
     * @return Collection<int, array{severity: string, icon: string, label: string, title: string, subtitle: string|null, url: string}>
     */
    public function items(): Collection
    {
        return collect()
            ->concat($this->overdueProjects())
            ->concat($this->unansweredQuotes())
            ->concat($this->missingDeposits())
            ->concat($this->planningConflicts())
            ->concat($this->waitingCustomers())
            ->concat($this->overdueTasks())
            ->sortBy(fn (array $item) => $item['severity'] === 'rood' ? 0 : 1)
            ->values();
    }

    public function count(): int
    {
        return $this->items()->count();
    }

    /**
     * Gecachete teller voor de navigatiebadge.
     */
    public static function cachedCount(): int
    {
        return Cache::remember('attention-count', 300, fn () => (new self)->count());
    }

    public static function forgetCount(): void
    {
        Cache::forget('attention-count');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function overdueProjects(): Collection
    {
        return Project::active()
            ->whereDate('end_date_expected', '<', today())
            ->with('customer')
            ->get()
            ->map(fn (Project $project) => [
                'severity' => 'rood',
                'icon' => 'wrench-screwdriver',
                'label' => 'Project loopt uit',
                'title' => $project->name,
                'subtitle' => 'Verwachte oplevering was '.$project->end_date_expected->translatedFormat('j M').' · '.$project->customer->name,
                'url' => route('projects.show', $project),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function unansweredQuotes(): Collection
    {
        if (! config('renovion.modules.quotes')) {
            return collect();
        }

        return Quote::open()
            ->whereNotNull('sent_at')
            ->where('sent_at', '<=', now()->subDays(3))
            ->with('customer')
            ->get()
            ->map(fn (Quote $quote) => [
                'severity' => $quote->daysOpen() >= 5 ? 'rood' : 'oranje',
                'icon' => 'document-text',
                'label' => 'Offerte niet opgevolgd',
                'title' => $quote->customer->name.' — € '.number_format((float) $quote->total, 0, ',', '.'),
                'subtitle' => $quote->number.' · '.$quote->daysOpen().' dagen open'.($quote->viewed_count > 0 ? ' · '.$quote->viewed_count.'× bekeken' : ''),
                'url' => route('quotes.show', $quote),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function missingDeposits(): Collection
    {
        return Project::active()
            ->whereNotNull('deposit_amount')
            ->whereNull('deposit_received_at')
            ->with('customer')
            ->get()
            ->filter(fn (Project $project) => (float) $project->deposit_amount > 0)
            ->map(fn (Project $project) => [
                'severity' => $project->start_date !== null && $project->start_date->lte(today()->addDays(4)) ? 'rood' : 'oranje',
                'icon' => 'currency-euro',
                'label' => 'Aanbetaling ontbreekt',
                'title' => $project->customer->name.' — € '.number_format((float) $project->deposit_amount, 0, ',', '.'),
                'subtitle' => $project->name.($project->start_date ? ' · start '.$project->start_date->translatedFormat('j M') : ''),
                'url' => route('projects.show', $project),
            ])
            ->values();
    }

    /**
     * Dubbele boekingen (zelfde vakman, zelfde dag, meerdere projecten) en
     * uitlopende projecten waarvan de vakman binnenkort elders is ingepland.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function planningConflicts(): Collection
    {
        $conflicts = collect();

        $doubleBookings = ScheduleEntry::where('type', ScheduleEntryType::Project)
            ->whereBetween('date', [today(), today()->addDays(14)])
            ->whereNotNull('project_id')
            ->with(['user', 'project'])
            ->get()
            ->groupBy(fn (ScheduleEntry $entry) => $entry->user_id.'|'.$entry->date->toDateString())
            ->filter(fn (Collection $entries) => $entries->pluck('project_id')->unique()->count() > 1);

        foreach ($doubleBookings as $entries) {
            /** @var ScheduleEntry $first */
            $first = $entries->first();
            $conflicts->push([
                'severity' => 'rood',
                'icon' => 'calendar',
                'label' => 'Planningconflict',
                'title' => $first->user->name.' staat dubbel gepland op '.$first->date->translatedFormat('D j M'),
                'subtitle' => $entries->map(fn (ScheduleEntry $entry) => $entry->project?->name)->filter()->unique()->implode(' én '),
                'url' => route('planning.index', ['week' => $first->date->toDateString()]),
            ]);
        }

        $overdueProjects = Project::active()
            ->whereDate('end_date_expected', '<', today())
            ->with('craftsmen')
            ->get();

        foreach ($overdueProjects as $project) {
            $craftsmanIds = $project->craftsmen->pluck('id');

            if ($craftsmanIds->isEmpty()) {
                continue;
            }

            $upcomingElsewhere = ScheduleEntry::whereIn('user_id', $craftsmanIds)
                ->where('project_id', '!=', $project->id)
                ->whereNotNull('project_id')
                ->whereBetween('date', [today(), today()->addDays(7)])
                ->with(['user', 'project'])
                ->orderBy('date')
                ->first();

            if ($upcomingElsewhere !== null) {
                $conflicts->push([
                    'severity' => 'rood',
                    'icon' => 'calendar',
                    'label' => 'Planningconflict',
                    'title' => $project->name.' loopt uit — '.$upcomingElsewhere->user->name.' staat '.$upcomingElsewhere->date->translatedFormat('D j M').' bij '.($upcomingElsewhere->project?->name ?? 'een ander project').' gepland',
                    'subtitle' => 'Planning aanpassen of iemand anders inzetten?',
                    'url' => route('planning.index', ['week' => $upcomingElsewhere->date->toDateString()]),
                ]);
            }
        }

        return $conflicts;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function waitingCustomers(): Collection
    {
        return Lead::open()
            ->whereNotNull('next_action_at')
            ->where('next_action_at', '<', now())
            ->with('customer')
            ->get()
            ->map(fn (Lead $lead) => [
                'severity' => 'oranje',
                'icon' => 'phone',
                'label' => 'Klant wacht op reactie',
                'title' => $lead->customer->name,
                'subtitle' => ($lead->next_action ?? 'Opvolgen').' · gepland '.$lead->next_action_at->translatedFormat('j M H:i'),
                'url' => route('leads.show', $lead),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function overdueTasks(): Collection
    {
        return Task::open()
            ->whereDate('deadline', '<', today())
            ->with(['customer', 'project', 'owner'])
            ->get()
            ->map(fn (Task $task) => [
                'severity' => 'rood',
                'icon' => 'clock',
                'label' => 'Deadline verlopen',
                'title' => $task->title,
                'subtitle' => collect([
                    $task->customer?->name ?? $task->project?->name,
                    'deadline '.$task->deadline->translatedFormat('j M'),
                    $task->owner?->name,
                ])->filter()->implode(' · '),
                'url' => route('tasks.index'),
            ]);
    }
}
