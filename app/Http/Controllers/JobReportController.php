<?php

namespace App\Http\Controllers;

use App\Enums\JobReportType;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\ChatChannel;
use App\Models\JobExecution;
use App\Models\JobReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * Meldingen vanaf de bouwplaats. Een melding gaat direct naar het projectkanaal
 * in de teamchat en blijft in de inbox staan tot de projectleider hem afhandelt.
 */
class JobReportController extends Controller
{
    public function store(Request $request, JobExecution $job): RedirectResponse
    {
        abort_unless($request->user()->id === $job->user_id, 403);

        $validated = $request->validate([
            'type' => ['required', new Enum(JobReportType::class)],
            'body' => ['required', 'string', 'max:2000'],
            'urgent' => ['nullable', 'boolean'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'max:10240'],
        ]);

        $type = JobReportType::from($validated['type']);

        $report = $job->reports()->create([
            'project_id' => $job->project_id,
            'reported_by' => $request->user()->id,
            'type' => $type,
            'body' => $validated['body'],
            'urgent' => (bool) ($validated['urgent'] ?? false),
            'status' => JobReport::STATUS_OPEN,
        ]);

        foreach ($validated['photos'] ?? [] as $file) {
            $report->photos()->create([
                'project_id' => $job->project_id,
                'uploaded_by' => $request->user()->id,
                'path' => $file->store('project-photos'),
                'caption' => $type->label(),
                'client_visible' => false,
            ]);
        }

        AuditLog::record($report, 'melding gemaakt', [], [
            'soort' => $type->label(),
            'spoed' => $report->urgent ? 'ja' : 'nee',
        ]);

        $this->postToProjectChannel($job, $report);

        $job->project?->customer?->recordEvent(
            TimelineEventType::Intern,
            'Melding van de bouwplaats: '.$type->label(),
            $report->body,
            $report,
        );

        return back()->with('success', $report->urgent
            ? 'Spoedmelding verstuurd. De projectleider krijgt hem direct te zien.'
            : 'Melding verstuurd naar de projectleider.');
    }

    public function resolve(Request $request, JobReport $report): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $validated = $request->validate([
            'resolution' => ['nullable', 'string', 'max:2000'],
        ]);

        $report->update([
            'status' => JobReport::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
            'resolution' => $validated['resolution'] ?? null,
        ]);

        AuditLog::record($report, 'melding afgehandeld', [], [
            'reactie' => $validated['resolution'] ?? null,
        ]);

        return back()->with('success', 'Melding afgehandeld.');
    }

    /**
     * De melding in het projectkanaal zetten, zodat iedereen die aan het project
     * werkt hem ziet zonder de inbox te hoeven openen.
     */
    private function postToProjectChannel(JobExecution $job, JobReport $report): void
    {
        if ($job->project_id === null) {
            return;
        }

        $channel = ChatChannel::where('project_id', $job->project_id)->first();

        if ($channel === null) {
            return;
        }

        $channel->messages()->create([
            'user_id' => $report->reported_by,
            'body' => ($report->urgent ? 'Spoed: ' : '')
                .$report->type->label().' bij '.($job->scheduleEntry?->displayTitle() ?? 'de klus')
                ."\n".$report->body,
        ]);
    }
}
