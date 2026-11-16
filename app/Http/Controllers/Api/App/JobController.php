<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\JobReportType;
use App\Enums\JobStatus;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\JobController as WebJobController;
use App\Models\Document;
use App\Models\JobExecution;
use App\Models\JobReport;
use App\Models\JobStep;
use App\Models\Photo;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\Support\App\FieldWork;
use App\Support\JobChecklistTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mijn klussen in de app (jobs/index en jobs/show): dezelfde groepen en dezelfde toegang als de website.
 * Status, stappen, foto's, meldingen en afronden gaan via de controllers van de website (JSON-antwoord).
 */
class JobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $subject = WebJobController::subject($request);
        $groups = WebJobController::groups($subject);

        return response()->json([
            'subject' => ['id' => $subject->id, 'name' => $subject->name, 'me' => $subject->id === $viewer->id],
            'colleagues' => WebJobController::colleagues($viewer)->map(fn (User $person) => [
                'id' => $person->id,
                'label' => $person->name.', '.($person->job_title ?: $person->role->label()),
            ])->values(),
            'today' => now()->translatedFormat('l j F'),
            'vandaag' => $groups['vandaag']->map(fn (array $row) => $this->card($row))->values(),
            'komend' => $groups['komend']->map(fn (array $row) => $this->card($row))->values(),
            'afgerond' => $groups['afgerond']->map(fn (array $row) => $this->card($row))->values(),
        ]);
    }

    public function show(Request $request, ScheduleEntry $scheduleEntry): JsonResponse
    {
        abort_if($scheduleEntry->project_id === null, 404);

        $job = JobExecution::forEntry($scheduleEntry);

        abort_unless($job->isAccessibleBy($request->user()), 403);

        $job->load([
            'user', 'steps.photos', 'reports.photos', 'reports.reporter', 'reports.resolver',
            'project.customer', 'project.projectLeader', 'project.documents',
        ]);
        $job->setRelation('scheduleEntry', $scheduleEntry);

        $project = $job->project;
        $leader = $project?->projectLeader;
        $workOrder = $job->workOrder()->first();
        $action = $job->status->primaryAction();
        $openSteps = $job->steps->whereNull('completed_at');
        $openStep = $job->steps->first(fn (JobStep $step) => ! $step->isDone());
        $photos = $job->steps->flatMap->photos;
        $address = collect([$project?->address, $project?->city])->filter();

        return response()->json([
            'id' => $scheduleEntry->id,
            'job_id' => $job->id,
            'title' => $scheduleEntry->displayTitle(),
            'project' => $project ? ['id' => $project->id, 'name' => $project->name, 'path' => route('projects.show', $project, false)] : null,
            'address' => $project?->address ? $address->join(', ') : null,
            'maps' => $project?->address ? 'https://maps.google.com/?q='.urlencode($address->join(' ')) : null,
            'when' => collect([$this->dates($scheduleEntry), $job->dayLabel(), $job->user?->name])->filter()->join(', '),
            'status' => $this->status($job->status),
            'progress' => $job->progress(),
            'steps_done' => $job->completedSteps(),
            'steps_total' => $job->steps->count(),
            'can_execute' => $request->user()->id === $job->user_id,
            'active' => $job->status->isActive(),
            'done' => $job->status === JobStatus::Klaar,
            // Onderweg => Start werk gaat via de veiligheidscheck (lmra).
            'action' => $action ? ['label' => $action['label'], 'icon' => $action['icon'], 'next' => $action['next']->value, 'lmra' => $job->status === JobStatus::Onderweg] : null,
            'lmra' => JobChecklistTemplates::lmra(),
            'finish' => [
                'hours' => $job->estimatedHours(),
                'missing_evidence' => $job->stepsMissingEvidence()->pluck('title')->values(),
                'open_steps' => $openSteps->count(),
                'open_titles' => $openSteps->take(3)->pluck('title')->join(', ').($openSteps->count() > 3 ? ' en meer' : ''),
            ],
            'work_order' => $workOrder ? [
                'number' => $workOrder->number,
                'path' => route('work-orders.show', $workOrder, false),
                'text' => $workOrder->status === WorkOrderStatus::Controle
                    ? 'wacht op controle door '.($leader?->name ?? 'de projectleider')
                    : $workOrder->status->label(),
            ] : null,
            'open_step' => $openStep?->id,
            'steps' => $job->steps->map(fn (JobStep $step) => [
                'id' => $step->id,
                'number' => $step->position + 1,
                'title' => $step->title,
                'hint' => $step->hint,
                'done' => $step->isDone(),
                'photo_required' => (bool) $step->photo_required,
                'needs_photo' => $step->needsPhoto(),
                'note' => $step->note,
                'photos' => $step->photos->map(fn (Photo $photo) => FieldWork::photo($photo, $step->title))->values(),
            ])->values(),
            'info' => [
                'entry_notes' => $scheduleEntry->notes,
                'project_notes' => $project?->notes,
                'leader' => $leader ? ['name' => $leader->name, 'phone' => $leader->phone ? str_replace(' ', '', $leader->phone) : null] : null,
                'customer' => $project?->customer ? [
                    'name' => $project->customer->name,
                    'phone' => $project->customer->phone ? str_replace(' ', '', $project->customer->phone) : null,
                ] : null,
                'documents' => ($project?->documents ?? collect())->take(8)->map(fn (Document $document) => [
                    'id' => $document->id,
                    'name' => $document->name,
                    'category' => $document->category?->label(),
                    'path' => route('documents.show', $document, false),
                ])->values(),
            ],
            'photos' => $photos->map(fn (Photo $photo) => FieldWork::photo($photo))->values(),
            'reports' => $job->reports->map(fn (JobReport $report) => [
                'id' => $report->id,
                'type' => ['label' => $report->type->label(), 'icon' => $report->type->icon(), 'classes' => $report->type->appBadgeClasses()],
                'urgent' => $report->urgent,
                'body' => $report->body,
                'open' => $report->isOpen(),
                'photos' => $report->photos->map(fn (Photo $photo) => FieldWork::photo($photo, $report->type->label()))->values(),
                'meta' => $report->created_at->translatedFormat('j M H:i').', '.$report->reporter?->name.', '.($report->isOpen()
                    ? 'wacht op reactie van '.($leader?->name ?? 'de projectleider')
                    : 'afgehandeld door '.$report->resolver?->name),
                'resolution' => $report->resolution,
            ])->values(),
            'report_types' => array_map(fn (JobReportType $type) => ['value' => $type->value, 'label' => $type->label(), 'icon' => $type->icon()], JobReportType::cases()),
            'can_resolve' => $request->user()->can('manage-crm'),
            'counts' => [
                'stappen' => $openSteps->count(),
                'fotos' => $photos->count(),
                'meldingen' => $job->openReports()->count(),
            ],
            'path' => route('jobs.show', $scheduleEntry, false),
        ]);
    }

    /** @param array{entry: ScheduleEntry, job: ?JobExecution, status: JobStatus} $row */
    private function card(array $row): array
    {
        $entry = $row['entry'];
        $job = $row['job'];
        $project = $entry->project;
        $end = $entry->end_date ?? $entry->date;
        $job?->setRelation('scheduleEntry', $entry);
        $openReports = $job?->openReports()->count() ?? 0;

        return [
            'id' => $entry->id,
            'path' => route('jobs.show', $entry, false),
            'title' => $entry->displayTitle(),
            'project' => $project?->name,
            'address' => $project?->address ? collect([$project->address, $project->city])->filter()->join(', ') : null,
            'when' => collect([
                $this->dates($entry),
                $job?->dayLabel() && $end->greaterThanOrEqualTo(today()) ? $job->dayLabel() : null,
            ])->filter()->join(', '),
            'status' => $this->status($row['status']),
            'progress' => $job?->progress(),
            'open_reports' => $openReports,
        ];
    }

    private function dates(ScheduleEntry $entry): string
    {
        $end = $entry->end_date ?? $entry->date;

        return $entry->date->translatedFormat('j M').($end->isSameDay($entry->date) ? '' : ' t/m '.$end->translatedFormat('j M'));
    }

    /** @return array{value: string, label: string, classes: string} */
    private function status(JobStatus $status): array
    {
        return ['value' => $status->value, 'label' => $status->label(), 'classes' => $status->appBadgeClasses()];
    }
}
