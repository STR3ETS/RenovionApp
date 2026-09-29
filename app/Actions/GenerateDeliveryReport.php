<?php

namespace App\Actions;

use App\Enums\PhaseStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\ChecklistItem;
use App\Models\DeliveryReport;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkPackage;

/**
 * Automatisch opleverrapport (briefing §13): bouwt een bevroren snapshot uit
 * projectdata, fasen, foto-bewijs, wijzigingen en restpunten. Hergenereren
 * kan zolang de klant nog niet heeft getekend.
 */
class GenerateDeliveryReport
{
    public function handle(Project $project, ?User $user = null): DeliveryReport
    {
        $project->load([
            'customer', 'quote.versions',
            'phases.workPackages.items.doneBy',
            'phases.workPackages.responsible',
            'phases.approver',
            'tasks' => fn ($query) => $query->open(),
            'tasks.owner',
        ]);

        $report = DeliveryReport::updateOrCreate(
            ['project_id' => $project->id],
            [
                'snapshot' => $this->snapshot($project),
                'generated_at' => now(),
                'generated_by' => $user?->id,
            ],
        );

        AuditLog::record($report, $report->wasRecentlyCreated ? 'aangemaakt' : 'hergenereerd', [], ['project' => $project->name]);
        $project->customer->recordEvent(
            TimelineEventType::Document,
            'Opleverrapport '.($report->wasRecentlyCreated ? 'aangemaakt' : 'bijgewerkt'),
            null,
            $report,
        );

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Project $project): array
    {
        $scopeBlok = collect($project->quote?->blocks ?? [])->firstWhere('key', 'scope');

        return [
            'project' => [
                'name' => $project->name,
                'customer' => $project->customer->name,
                'address' => trim(($project->address ?? '').', '.($project->city ?? ''), ', '),
                'scope' => $scopeBlok['body'] ?? $project->notes,
                'quote_number' => $project->quote?->number,
                'start_date' => $project->start_date?->toDateString(),
                'end_date_expected' => $project->end_date_expected?->toDateString(),
                'end_date_actual' => ($project->end_date_actual ?? today())->toDateString(),
                'deviation_days' => $project->end_date_expected !== null
                    ? (int) round($project->end_date_expected->diffInDays($project->end_date_actual ?? today(), false))
                    : null,
                'progress' => $project->progress,
            ],
            'phases' => $project->phases->map(fn (ProjectPhase $phase) => [
                'name' => $phase->name,
                'status' => $phase->status->label(),
                'done' => $phase->status === PhaseStatus::Gereed,
                'completed_at' => $phase->completed_at?->toDateString(),
                'approved_by' => $phase->approver?->name,
                'client_approved_at' => $phase->client_approved_at?->toDateString(),
            ])->values()->all(),
            'work' => $project->phases
                ->flatMap(fn (ProjectPhase $phase) => $phase->workPackages->map(fn (WorkPackage $package) => [
                    'phase' => $phase->name,
                    'name' => $package->name,
                    'status' => $package->status->label(),
                    'done' => $package->isDone(),
                    'responsible' => $package->responsible?->name,
                    'items' => $package->items
                        ->filter(fn (ChecklistItem $item) => $item->isDone())
                        ->map(fn (ChecklistItem $item) => [
                            'label' => $item->label,
                            'done_by' => $item->doneBy?->name,
                            'done_at' => $item->done_at?->toDateString(),
                        ])->values()->all(),
                    'photo_ids' => $package->photos()
                        ->where('client_visible', true)
                        ->latest()
                        ->limit(6)
                        ->pluck('id')
                        ->all(),
                ]))->values()->all(),
            'general_photo_ids' => $project->photos()
                ->where('client_visible', true)
                ->whereNull('work_package_id')
                ->latest()
                ->limit(8)
                ->pluck('id')
                ->all(),
            'changes' => collect($project->quote?->versions ?? [])
                ->filter(fn ($version) => filled($version->note))
                ->map(fn ($version) => [
                    'version' => 'v'.$version->version,
                    'note' => $version->note,
                    'date' => $version->created_at->toDateString(),
                ])->values()->all(),
            'leftovers' => collect()
                ->concat($project->phases->flatMap->workPackages
                    ->reject(fn (WorkPackage $package) => $package->isDone())
                    ->map(fn (WorkPackage $package) => [
                        'type' => 'Werkpakket',
                        'title' => $package->name,
                        'owner' => $package->responsible?->name,
                        'deadline' => $package->deadline?->toDateString(),
                        'status' => $package->status->label(),
                    ]))
                ->concat($project->tasks->map(fn (Task $task) => [
                    'type' => 'Taak',
                    'title' => $task->title,
                    'owner' => $task->owner?->name,
                    'deadline' => $task->deadline?->toDateString(),
                    'status' => $task->status->label(),
                ]))
                ->values()->all(),
            'warranty' => 'Renovion staat voor kwaliteit: geen half werk en vage beloftes. Op de uitvoering geldt 5 jaar garantie; op materialen gelden de fabrieksgaranties. '
                .'Garantiepunten of nazorgvragen meldt u eenvoudig via uw klantportaal of via info@renovion.nl — we plannen herstel binnen redelijke termijn in.',
        ];
    }
}
