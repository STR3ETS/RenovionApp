<?php

namespace App\Actions;

use App\Enums\TaskStatus;
use App\Enums\TimelineEventType;
use App\Enums\WorkOrderStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * De projectleider keurt een werkbon goed. Dat is het moment waarop de uren
 * echt gaan tellen: ze worden tegen het tarief van die medewerker omgerekend
 * naar kosten op het project, de voortgang schuift op naar rato van de begrote
 * uren van deze bon, en een eventueel gekoppelde taak gaat op afgerond.
 *
 * Het tarief wordt op de bon vastgelegd, zodat een latere tariefwijziging de
 * historie niet verandert.
 */
class ApproveWorkOrder
{
    /**
     * @return array{hours: float, cost: float, progress_before: int, progress_after: int}
     */
    public function handle(WorkOrder $order, User $approver): array
    {
        return DB::transaction(function () use ($order, $approver) {
            $order->load(['project', 'user', 'task']);

            $hours = (float) $order->hours;
            $rate = $order->hourly_rate !== null
                ? (float) $order->hourly_rate
                : ($order->user?->rate() ?? 50.0);

            $project = $order->project;
            $before = (int) $project->progress;
            $share = $this->progressShare($order);
            $after = min(100, $before + $share);

            $order->update([
                'status' => WorkOrderStatus::Goedgekeurd,
                'hourly_rate' => $rate,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $cost = round($hours * $rate, 2);

            $project->update(['progress' => $after]);

            if ($order->task !== null && $order->task->status !== TaskStatus::Afgerond) {
                $order->task->update(['status' => TaskStatus::Afgerond, 'completed_at' => now()]);
            }

            AuditLog::record($order, 'werkbon goedgekeurd', [
                'status' => WorkOrderStatus::Controle->value,
                'voortgang' => $before,
            ], [
                'status' => WorkOrderStatus::Goedgekeurd->value,
                'uren' => $hours,
                'kosten' => $cost,
                'voortgang' => $after,
            ]);

            $project->customer?->recordEvent(
                TimelineEventType::Projectupdate,
                "Werkbon {$order->number} goedgekeurd",
                "{$hours} uur van {$order->user?->name} geboekt, voortgang {$before} naar {$after} procent.",
                $order,
            );

            return [
                'hours' => $hours,
                'cost' => $cost,
                'progress_before' => $before,
                'progress_after' => $after,
            ];
        });
    }

    public function reject(WorkOrder $order, User $approver, string $reason): void
    {
        $order->update([
            'status' => WorkOrderStatus::Uitvoering,
            'rejection_reason' => $reason,
            'submitted_at' => null,
        ]);

        AuditLog::record($order, 'werkbon afgekeurd', [], ['reden' => $reason]);
    }

    /**
     * Hoeveel voortgang deze bon oplevert: het aandeel van de begrote uren van
     * deze bon in alle begrote uren van het project, afgetopt op 25 procent zodat
     * één bon het project nooit in één keer afmaakt.
     */
    private function progressShare(WorkOrder $order): int
    {
        $total = (float) WorkOrder::where('project_id', $order->project_id)->sum('budget_hours');
        $own = (float) $order->budget_hours;

        if ($own <= 0) {
            return 0;
        }

        $base = max($total, 80.0);

        return min(25, (int) round($own / $base * 100));
    }
}
