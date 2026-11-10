<?php

namespace App\Actions;

use App\Enums\JobStatus;
use App\Enums\TimelineEventType;
use App\Enums\WorkOrderStatus;
use App\Models\AuditLog;
use App\Models\JobExecution;
use App\Models\JobStep;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * De uitvoerder rondt zijn klus af. Dat levert direct een werkbon op die ter
 * controle bij de projectleider komt: uren, materiaal, de afgevinkte checklist
 * en het foto-bewijs staan erin, met eventueel de handtekening van de klant.
 *
 * Afronden kan niet zolang een stap met verplichte foto nog geen bewijs heeft.
 * Open stappen mogen wel: dan is het werk nog niet klaar en vertelt de werkbon
 * dat eerlijk.
 */
class CompleteJob
{
    /**
     * @param  array{hours: float, materials?: string|null, notes?: string|null, signed_name?: string|null}  $input
     */
    public function handle(JobExecution $job, User $user, array $input): WorkOrder
    {
        $job->load(['steps.photos', 'scheduleEntry', 'project']);

        if ($job->stepsMissingEvidence()->isNotEmpty()) {
            throw new \RuntimeException('Maak eerst de verplichte foto bij elke stap die dat vraagt.');
        }

        return DB::transaction(function () use ($job, $user, $input) {
            $entry = $job->scheduleEntry;
            $hours = round((float) $input['hours'], 2);

            $order = WorkOrder::firstOrNew(['job_execution_id' => $job->id]);

            $order->fill([
                'number' => $order->number ?? WorkOrder::nextNumber(),
                'project_id' => $job->project_id,
                'user_id' => $job->user_id,
                'schedule_entry_id' => $job->schedule_entry_id,
                'title' => $entry?->displayTitle() ?? 'Werkbon',
                'date' => $entry?->date ?? today(),
                'end_date' => $entry?->end_date,
                'status' => WorkOrderStatus::Controle,
                'hours' => $hours,
                'budget_hours' => $order->budget_hours ?: $this->budgetHours($job),
                'materials' => $this->parseMaterials($input['materials'] ?? null),
                'checklist' => $this->checklistSnapshot($job),
                'notes' => $input['notes'] ?? null,
                'submitted_at' => now(),
            ]);

            if (filled($input['signed_name'] ?? null)) {
                $order->signed_name = $input['signed_name'];
                $order->signed_at = now();
            }

            $order->save();

            $job->update([
                'status' => JobStatus::Klaar,
                'finished_at' => now(),
                'hours' => $hours,
                'notes' => $input['notes'] ?? $job->notes,
            ]);

            AuditLog::record($order, 'werkbon ingediend', [], [
                'uren' => $hours,
                'stappen' => $job->completedSteps().' van '.$job->steps->count(),
                'ondertekend' => $order->isSigned() ? $order->signed_name : null,
            ]);

            $project = $job->project;

            if ($project?->customer !== null) {
                $project->customer->recordEvent(
                    TimelineEventType::Projectupdate,
                    "{$user->name} rondde \"{$order->title}\" af",
                    "{$job->progress()} procent van de stappen afgevinkt, {$hours} uur geboekt. Werkbon {$order->number} staat ter controle.",
                    $order,
                );
            }

            return $order;
        });
    }

    /**
     * De begrote uren voor dit werk: acht uur per werkdag van het planningsblok.
     * Zodra de begrotingsmodule er is, komt dit uit de begrote arbeidsuren.
     */
    private function budgetHours(JobExecution $job): float
    {
        $entry = $job->scheduleEntry;

        if ($entry === null) {
            return 8.0;
        }

        $end = $entry->end_date ?? $entry->date;

        return ($entry->date->diffInWeekdays($end) + 1) * 8.0;
    }

    /**
     * Materiaal komt als vrije tekst binnen, een regel per post: "6 st gipsplaat"
     * of "2,5 m2 tegels". Wat we niet kunnen ontleden bewaren we als omschrijving,
     * zodat er nooit informatie verdwijnt.
     *
     * @return list<array{quantity: float, unit: string, description: string}>
     */
    private function parseMaterials(?string $text): array
    {
        // Een nieuwe regel scheidt posten, en "6 platen, 2 zakken lijm" ook. Let op
        // de spatie: zonder die eis knipt dit "2,5 m2" middendoor.
        $lines = collect(preg_split('/\r?\n|,\s+(?=\d)/', (string) $text))
            ->map(fn (string $line) => trim($line))
            ->filter();

        return $lines->map(function (string $line) {
            if (preg_match('/^([\d.,]+)\s*([a-zA-Z0-9²]{1,4})?\s+(.+)$/u', $line, $m) === 1) {
                return [
                    'quantity' => (float) str_replace(',', '.', $m[1]),
                    'unit' => $m[2] ?: 'st',
                    'description' => trim($m[3]),
                ];
            }

            return ['quantity' => 1.0, 'unit' => 'pst', 'description' => $line];
        })->values()->all();
    }

    /**
     * De checklist bevriest in de werkbon: wat is afgevinkt, met welke notitie en
     * hoeveel foto's. Latere wijzigingen aan de klus raken deze bon niet meer.
     *
     * @return list<array{title: string, done: bool, note: ?string, photos: int}>
     */
    private function checklistSnapshot(JobExecution $job): array
    {
        return $job->steps->map(fn (JobStep $step) => [
            'title' => $step->title,
            'done' => $step->isDone(),
            'note' => $step->note,
            'photos' => $step->photos->count(),
        ])->values()->all();
    }
}
