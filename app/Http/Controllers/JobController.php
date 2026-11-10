<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Models\JobExecution;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\Support\JobChecklistTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Mijn klussen: de werkweergave voor op de bouwplaats. Een uitvoerder ziet hier
 * zijn eigen planning als klussen, met vandaag bovenaan. Wie de CRM mag beheren
 * kan de weergave van een collega opvragen, zodat een projectleider mee kan
 * kijken zonder op diens telefoon te hoeven.
 */
class JobController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->subject($request);

        $entries = ScheduleEntry::where('user_id', $user->id)
            ->whereNotNull('project_id')
            ->with(['project.customer', 'project.projectLeader'])
            ->orderBy('date')
            ->get();

        // Eén query voor alle bestaande klussen; blokken zonder klus staan gepland.
        $jobs = JobExecution::whereIn('schedule_entry_id', $entries->pluck('id'))
            ->with(['steps.photos', 'reports'])
            ->get()
            ->keyBy('schedule_entry_id');

        $rows = $entries->map(fn (ScheduleEntry $entry) => [
            'entry' => $entry,
            'job' => $jobs->get($entry->id),
            'status' => $jobs->get($entry->id)?->status ?? JobStatus::Gepland,
        ]);

        return view('jobs.index', [
            'subject' => $user,
            'colleagues' => $request->user()->can('manage-crm')
                ? User::query()->where('active', true)->orderBy('name')->get()->filter->isPlannable()
                : collect(),
            'vandaag' => $this->today($rows),
            'komend' => $rows->filter(fn (array $row) => $row['entry']->date->isAfter(today())
                && $row['entry']->date->lessThanOrEqualTo(today()->addDays(21))),
            'afgerond' => $rows->filter(fn (array $row) => $row['status'] === JobStatus::Klaar
                && $row['entry']->date->greaterThanOrEqualTo(today()->subDays(14))),
        ]);
    }

    public function show(Request $request, ScheduleEntry $scheduleEntry): View
    {
        abort_if($scheduleEntry->project_id === null, 404);

        $job = JobExecution::forEntry($scheduleEntry);

        abort_unless($job->isAccessibleBy($request->user()), 403);

        $job->load([
            'steps.photos', 'reports.photos', 'reports.reporter', 'reports.resolver',
            'project.customer', 'project.projectLeader', 'project.documents',
        ]);

        return view('jobs.show', [
            'entry' => $scheduleEntry,
            'job' => $job,
            'project' => $job->project,
            'workOrder' => $job->workOrder()->first(),
            'lmra' => JobChecklistTemplates::lmra(),
            'tab' => $request->string('tab', 'stappen')->toString(),
            'canExecute' => $request->user()->id === $job->user_id,
        ]);
    }

    /**
     * Klussen van vandaag: blokken die vandaag lopen en nog niet klaar zijn, plus
     * wat gisteren of eerder begon en nog openstaat. Zo blijft werk dat uitloopt
     * in beeld in plaats van uit de lijst te vallen.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function today(Collection $rows): Collection
    {
        return $rows->filter(function (array $row) {
            $entry = $row['entry'];
            $end = $entry->end_date ?? $entry->date;

            return $row['status'] !== JobStatus::Klaar
                && $entry->date->lessThanOrEqualTo(today())
                && ($end->greaterThanOrEqualTo(today()) || $row['status']->isActive());
        });
    }

    /**
     * Wiens klussen bekijken we? Standaard je eigen. Beheerders mogen met
     * ?medewerker= meekijken bij een collega.
     */
    private function subject(Request $request): User
    {
        $id = $request->integer('medewerker');

        if ($id === 0 || ! $request->user()->can('manage-crm')) {
            return $request->user();
        }

        return User::findOr($id, fn () => $request->user());
    }
}
