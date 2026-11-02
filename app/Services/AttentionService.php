<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\PhaseStatus;
use App\Enums\ScheduleEntryType;
use App\Models\JobReport;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Quote;
use App\Models\ScheduleEntry;
use App\Models\ServiceTicket;
use App\Models\SnagItem;
use App\Models\Task;
use App\Models\WorkOrder;
use App\Models\WorkPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * De Aandacht-module (briefing §16): alleen uitzonderingen, geen lijst met
 * vijftig dingen die goed gaan. Elk item is een beslissing of actie waard.
 */
class AttentionService
{
    /**
     * De soorten in de inbox. "Te doen" is geen soort maar een doorsnede: alles
     * wat een handeling van jou vraagt, dus iedere soort behalve informatie.
     */
    public const KINDS = [
        'todo' => 'Te doen',
        'actie' => 'Actie nodig',
        'bevestigen' => 'Ter bevestiging',
        'service' => 'Service',
        'info' => 'Ter info',
    ];

    /**
     * @return Collection<int, array{severity: string, kind: string, icon: string, label: string, title: string, subtitle: string|null, url: string}>
     */
    public function items(): Collection
    {
        return collect()
            ->concat($this->overdueProjects())
            ->concat($this->blockedWork())
            ->concat($this->openSiteReports())
            ->concat($this->workOrdersAwaitingApproval())
            ->concat($this->serviceTickets())
            ->concat($this->overdueSnags())
            ->concat($this->unansweredQuotes())
            ->concat($this->missingDeposits())
            ->concat($this->planningConflicts())
            ->concat($this->waitingCustomers())
            ->concat($this->silentLeads())
            ->concat($this->overdueTasks())
            // Zonder expliciete soort is het gewoon actie.
            ->map(fn (array $item) => [...$item, 'kind' => $item['kind'] ?? 'actie'])
            ->sortBy(fn (array $item) => [
                $item['kind'] === 'info' ? 1 : 0,
                $item['severity'] === 'rood' ? 0 : 1,
            ])
            ->values();
    }

    /**
     * Meldingen vanaf de bouwplaats die nog open staan. Spoed betekent dat het
     * werk stilligt, dus dat is altijd rood.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function openSiteReports(): Collection
    {
        return JobReport::open()
            ->with(['project', 'reporter', 'job.scheduleEntry'])
            ->get()
            ->map(fn (JobReport $report) => [
                'severity' => $report->urgent || $report->type->isSevere() ? 'rood' : 'oranje',
                'kind' => 'actie',
                'icon' => $report->type->icon(),
                'label' => $report->urgent ? 'Spoedmelding bouwplaats' : 'Melding bouwplaats',
                'title' => $report->type->label().': '.str($report->body)->limit(60)->value(),
                'subtitle' => collect([
                    $report->project?->name,
                    $report->reporter->name,
                    $report->created_at->diffForHumans(),
                ])->filter()->join(', '),
                'url' => $report->job?->schedule_entry_id !== null
                    ? route('jobs.show', [$report->job->schedule_entry_id, 'tab' => 'meldingen'])
                    : route('projects.show', $report->project),
            ]);
    }

    /**
     * Nieuwe servicemeldingen. Een spoedmelding betekent lekkage of een onveilige
     * situatie, dus die kan niet wachten.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function serviceTickets(): Collection
    {
        return ServiceTicket::where('status', ServiceTicket::STATUS_NEW)
            ->with(['customer', 'project'])
            ->get()
            ->map(fn (ServiceTicket $ticket) => [
                'severity' => $ticket->priority === 'spoed' || $ticket->daysOpen() >= 3 ? 'rood' : 'oranje',
                'kind' => 'service',
                'icon' => 'wrench',
                'label' => $ticket->priority === 'spoed' ? 'Spoedmelding service' : 'Servicemelding',
                'title' => $ticket->title,
                'subtitle' => collect([
                    $ticket->customer->name,
                    $ticket->project?->name,
                    $ticket->warranty ? 'onder garantie' : 'buiten garantie',
                    $ticket->daysOpen().' '.($ticket->daysOpen() === 1 ? 'dag' : 'dagen').' open',
                ])->filter()->join(', '),
                'url' => route('service.index'),
            ]);
    }

    /**
     * Restpunten waarvan de hersteldatum voorbij is. Die houden de oplevering
     * tegen, want je komt niet bij de klantinspectie met open punten.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function overdueSnags(): Collection
    {
        return SnagItem::open()
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', today())
            ->with(['project', 'assignee', 'subcontractor'])
            ->get()
            ->map(fn (SnagItem $snag) => [
                'severity' => 'oranje',
                'kind' => 'actie',
                'icon' => 'exclamation-triangle',
                'label' => 'Restpunt over de datum',
                'title' => $snag->title,
                'subtitle' => collect([
                    $snag->project->name,
                    $snag->location,
                    $snag->ownerName(),
                    'moest voor '.$snag->due_on->translatedFormat('j M'),
                ])->filter()->join(', '),
                'url' => route('quality.show', $snag->project),
            ]);
    }

    /**
     * Werkbonnen die op goedkeuring wachten. Tot die goedkeuring tellen de uren
     * nergens mee, dus blijft het beeld van kosten en voortgang incompleet.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function workOrdersAwaitingApproval(): Collection
    {
        return WorkOrder::awaitingApproval()
            ->with(['project', 'user'])
            ->get()
            ->map(fn (WorkOrder $order) => [
                'severity' => $order->submitted_at?->lt(today()->subDays(3)) ? 'rood' : 'oranje',
                'kind' => 'bevestigen',
                'icon' => 'wrench',
                'label' => 'Werkbon ter controle',
                'title' => $order->number.': '.$order->title,
                'subtitle' => collect([
                    $order->project->name,
                    $order->user?->name,
                    rtrim(rtrim(number_format((float) $order->hours, 2, ',', '.'), '0'), ',').' uur',
                ])->filter()->join(', '),
                'url' => route('work-orders.show', $order),
            ]);
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
                'subtitle' => 'Verwachte oplevering was '.$project->end_date_expected->translatedFormat('j M').', '.$project->customer->name,
                'url' => route('projects.show', $project),
            ]);
    }

    /**
     * Geblokkeerde fasen/werkpakketten en werkpakketten over hun deadline
     * (briefing §3: Aandacht toont te laat en blokkades).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function blockedWork(): Collection
    {
        $blockedPhases = ProjectPhase::where('status', PhaseStatus::Geblokkeerd)
            ->whereHas('project', fn ($query) => $query->active())
            ->with('project')
            ->get()
            ->map(fn (ProjectPhase $phase) => [
                'severity' => 'rood',
                'icon' => 'exclamation-triangle',
                'label' => 'Fase geblokkeerd',
                'title' => $phase->project->name.' - '.$phase->name,
                'subtitle' => 'Blokkade oplossen om het project door te laten lopen',
                'url' => route('projects.show', $phase->project),
            ]);

        $problemPackages = WorkPackage::where('status', '!=', PhaseStatus::Gereed)
            ->whereHas('project', fn ($query) => $query->active())
            ->where(fn ($query) => $query
                ->where('status', PhaseStatus::Geblokkeerd)
                ->orWhereDate('deadline', '<', today()))
            ->with(['project', 'responsible'])
            ->get()
            ->map(fn (WorkPackage $package) => [
                'severity' => $package->status === PhaseStatus::Geblokkeerd ? 'rood' : 'oranje',
                'icon' => 'wrench-screwdriver',
                'label' => $package->status === PhaseStatus::Geblokkeerd ? 'Werkpakket geblokkeerd' : 'Werkpakket over deadline',
                'title' => $package->name,
                'subtitle' => collect([
                    $package->project->name,
                    $package->deadline ? 'deadline '.$package->deadline->translatedFormat('j M') : null,
                    $package->responsible?->name,
                ])->filter()->implode(', '),
                'url' => route('work-packages.show', $package),
            ]);

        return $blockedPhases->concat($problemPackages);
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
                'title' => $quote->customer->name.' - € '.number_format((float) $quote->total, 0, ',', '.'),
                'subtitle' => $quote->number.', '.$quote->daysOpen().' dagen open'.($quote->viewed_count > 0 ? ', '.$quote->viewed_count.'× bekeken' : ''),
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
                'title' => $project->customer->name.' - € '.number_format((float) $project->deposit_amount, 0, ',', '.'),
                'subtitle' => $project->name.($project->start_date ? ', start '.$project->start_date->translatedFormat('j M') : ''),
                'url' => route('projects.show', $project),
            ])
            ->values();
    }

    /**
     * Dubbele boekingen (zelfde uitvoerder, zelfde dag, meerdere projecten) en
     * uitlopende projecten waarvan de uitvoerder binnenkort elders is ingepland.
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
                    'title' => $project->name.' loopt uit - '.$upcomingElsewhere->user->name.' staat '.$upcomingElsewhere->date->translatedFormat('D j M').' bij '.($upcomingElsewhere->project?->name ?? 'een ander project').' gepland',
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
                'subtitle' => ($lead->next_action ?? 'Opvolgen').', gepland '.$lead->next_action_at->translatedFormat('j M H:i'),
                'url' => route('leads.show', $lead),
            ]);
    }

    /**
     * Stille aanvragen (briefing §4): open, geen volgende actie gepland en al
     * minstens 3 dagen geen contactmoment. Nova bewaakt dat er contact blijft.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function silentLeads(): Collection
    {
        return Lead::open()
            ->where('status', '!=', LeadStatus::OnHold)
            ->whereNull('next_action_at')
            ->whereRaw('coalesce(last_contact_at, created_at) <= ?', [now()->subDays(3)])
            ->with('customer')
            ->get()
            ->map(fn (Lead $lead) => [
                'severity' => 'oranje',
                'icon' => 'phone',
                'label' => 'Aanvraag zonder opvolging',
                'title' => $lead->customer->name,
                'subtitle' => ($lead->last_contact_at === null
                    ? 'nog geen contactmoment sinds de aanvraag ('.(int) $lead->created_at->diffInDays().' dagen)'
                    : 'laatste contact '.$lead->last_contact_at->translatedFormat('j M')).', geen volgende actie gepland',
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
                ])->filter()->implode(', '),
                'url' => route('tasks.index'),
            ]);
    }
}
