<?php

namespace App\Actions;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Rfq;
use App\Models\RfqBid;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Een prijsaanvraag gunnen. Dat is het moment waarop een bieding een
 * verplichting wordt: het bedrag staat vanaf nu in de prognose van het project,
 * ook al is er nog geen factuur.
 *
 * Gunnen mag alleen aan een partij met een rond dossier. Wie geen geldige VCA of
 * verzekering heeft, mag de bouwplaats niet op, dus ook geen opdracht krijgen.
 */
class AwardRfq
{
    public function handle(Rfq $rfq, RfqBid $bid, User $user): string
    {
        $subcontractor = $bid->subcontractor;

        if (! $subcontractor->mayBeInvited()) {
            throw new \RuntimeException(
                "Het dossier van {$subcontractor->name} is niet rond: {$subcontractor->statusLabel()}. "
                .'Vul dat eerst aan voordat je gunt.'
            );
        }

        return DB::transaction(function () use ($rfq, $bid, $subcontractor, $user) {
            $rfq->update([
                'status' => Rfq::STATUS_AWARDED,
                'awarded_to' => $subcontractor->id,
                'order_number' => $rfq->order_number ?? Rfq::nextOrderNumber(),
                'awarded_on' => today(),
                'starts_on' => $rfq->starts_on ?? $bid->can_start_on,
            ]);

            $subcontractor->increment('assignments');

            // De opdrachtbevestiging moet getekend terugkomen voordat er iemand start.
            Task::create([
                'title' => "Opdrachtbevestiging {$rfq->order_number} laten tekenen",
                'note' => $subcontractor->name.', EUR '.number_format((float) $bid->price, 2, ',', '.'),
                'project_id' => $rfq->project_id,
                'owner_id' => $user->id,
                'deadline' => today()->addDays(2),
                'status' => TaskStatus::TeDoen,
                'priority' => TaskPriority::Hoog,
            ]);

            $savings = $rfq->savings();

            AuditLog::record($rfq, 'prijsaanvraag gegund', [], [
                'partner' => $subcontractor->name,
                'prijs' => (float) $bid->price,
                'budget' => (float) $rfq->budget,
                'verschil' => $savings,
                'opdrachtnummer' => $rfq->order_number,
            ]);

            $rfq->project->customer?->recordEvent(
                TimelineEventType::Wijziging,
                "{$rfq->number} gegund aan {$subcontractor->name}",
                sprintf(
                    'EUR %s tegenover EUR %s begroot. Opdracht %s.',
                    number_format((float) $bid->price, 2, ',', '.'),
                    number_format((float) $rfq->budget, 2, ',', '.'),
                    $rfq->order_number,
                ),
                $rfq,
            );

            return sprintf(
                'Gegund aan %s voor EUR %s. Dat is EUR %s %s de begroting en staat nu als verplichting in de prognose.',
                $subcontractor->name,
                number_format((float) $bid->price, 2, ',', '.'),
                number_format(abs($savings ?? 0), 2, ',', '.'),
                ($savings ?? 0) >= 0 ? 'onder' : 'boven',
            );
        });
    }
}
