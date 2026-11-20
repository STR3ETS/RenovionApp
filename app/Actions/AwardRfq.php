<?php

namespace App\Actions;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\ProjectAgreement;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RfqBid;
use App\Models\SubcontractorLog;
use App\Models\Task;
use App\Models\User;
use App\Support\Margin;
use Illuminate\Support\Facades\DB;

/**
 * Een prijsaanvraag gunnen. Dat is het moment waarop een bieding een
 * verplichting wordt: er ontstaat direct een inkooporder met fotoplicht, en
 * dat bedrag staat vanaf nu in de prognose van het project.
 *
 * Twee dingen houden het gunnen tegen:
 * - een dossier dat niet rond is. Wie geen geldige VCA of verzekering heeft,
 *   mag de bouwplaats niet op, dus ook geen opdracht krijgen.
 * - een marge onder de bodem. Dan kan het alleen met een reden én akkoord van
 *   de directie, en dat leggen we vast bij het bod.
 */
class AwardRfq
{
    public function handle(Rfq $rfq, RfqBid $bid, User $user, ?string $reason = null): string
    {
        $subcontractor = $bid->subcontractor;

        if (! $subcontractor->mayBeInvited()) {
            throw new \RuntimeException(
                "Het dossier van {$subcontractor->name} is niet rond: {$subcontractor->statusLabel()}. "
                .'Vul dat eerst aan voordat je gunt.'
            );
        }

        $margin = $bid->marginPct();
        $approval = null;

        if ($margin !== null && $margin < Margin::floor()) {
            $text = 'De marge op dit bod is '.Margin::format($margin).', onder de bodem van '.Margin::format(Margin::floor()).'.';

            if (blank($reason)) {
                throw new \RuntimeException($text.' Gunnen kan alleen met een reden.');
            }

            if (! $user->role->isManagement()) {
                throw new \RuntimeException($text.' Daarvoor is akkoord van de directie nodig: laat de directie dit bod gunnen.');
            }

            $approval = [
                'door' => $user->name,
                'user_id' => $user->id,
                'reden' => $reason,
                'pct' => $margin,
                'op' => now()->toIso8601String(),
            ];
        }

        return DB::transaction(function () use ($rfq, $bid, $subcontractor, $user, $margin, $approval) {
            $rfq->update([
                'status' => Rfq::STATUS_AWARDED,
                'awarded_to' => $subcontractor->id,
                'order_number' => $rfq->order_number ?? Rfq::nextOrderNumber(),
                'awarded_on' => today(),
                'starts_on' => $rfq->starts_on ?? $bid->can_start_on,
            ]);

            $subcontractor->increment('assignments');

            // De inkooporder ontstaat vanzelf. Fotoplicht: zonder foto's voor,
            // tijdens en na uit de werkweergave keuren we de factuur niet goed.
            $order = PurchaseOrder::create([
                'number' => PurchaseOrder::nextNumber(),
                'project_id' => $rfq->project_id,
                'supplier' => $subcontractor->name,
                'subcontractor_id' => $subcontractor->id,
                'rfq_id' => $rfq->id,
                'photo_required' => true,
                'reference' => $rfq->order_number,
                'status' => PurchaseOrder::STATUS_SENT,
                'ordered_on' => today(),
                'expected_on' => $rfq->starts_on,
                'chapter_code' => $rfq->chapter_code,
                'category' => 'onderaanneming',
                'notes' => 'Ontstaan uit '.$rfq->number.'. Fotoplicht: de factuur wordt pas goedgekeurd met foto\'s voor, tijdens en na uit de werkweergave.',
                'created_by' => $user->id,
            ]);

            $order->lines()->create([
                'description' => $rfq->title,
                'quantity' => 1,
                'unit' => 'pst',
                'unit_price' => $bid->price,
                'position' => 1,
            ]);

            $bid->update(['purchase_order_id' => $order->id, 'margin_approval' => $approval]);

            // De overeenkomst voor dit project staat klaar om vastgelegd te worden.
            ProjectAgreement::firstOrCreate([
                'project_id' => $rfq->project_id,
                'subcontractor_id' => $subcontractor->id,
                'rfq_id' => $rfq->id,
            ]);

            // Het logboek van elke partij die meedeed: gekozen en niet gekozen.
            SubcontractorLog::note($subcontractor, sprintf(
                'Gegund: %s %s, EUR %s, opdracht %s',
                $rfq->number,
                $rfq->title,
                number_format((float) $bid->price, 2, ',', '.'),
                $rfq->order_number,
            ), $rfq->project_id, $user);

            $others = $rfq->invited->merge($rfq->bids->pluck('subcontractor'))
                ->filter()
                ->unique('id')
                ->reject(fn ($other) => $other->id === $subcontractor->id);

            foreach ($others as $other) {
                SubcontractorLog::note(
                    $other,
                    "Niet gegund: {$rfq->number} {$rfq->title}, gegund aan {$subcontractor->name}",
                    $rfq->project_id,
                    $user,
                );
            }

            // De opdrachtbevestiging moet getekend terugkomen voordat er iemand start.
            Task::create([
                'title' => "Opdrachtbevestiging {$rfq->order_number} laten tekenen",
                'note' => $subcontractor->name.', EUR '.number_format((float) $bid->price, 2, ',', '.').', inkooporder '.$order->number,
                'project_id' => $rfq->project_id,
                'owner_id' => $user->id,
                'deadline' => today()->addDays(2),
                'status' => TaskStatus::TeDoen,
                'priority' => TaskPriority::Hoog,
            ]);

            $savings = $rfq->savings();

            AuditLog::record($rfq, 'prijsaanvraag gegund', [], array_filter([
                'partner' => $subcontractor->name,
                'prijs' => (float) $bid->price,
                'budget' => (float) $rfq->budget,
                'verschil' => $savings,
                'opdrachtnummer' => $rfq->order_number,
                'inkooporder' => $order->number,
                'marge' => $margin,
                'marge_akkoord' => $approval === null ? null : $approval['door'].': '.$approval['reden'],
            ], fn ($value) => $value !== null));

            $rfq->project->customer?->recordEvent(
                TimelineEventType::Wijziging,
                "{$rfq->number} gegund aan {$subcontractor->name}",
                sprintf(
                    'EUR %s tegenover EUR %s begroot. Opdracht %s, inkooporder %s.',
                    number_format((float) $bid->price, 2, ',', '.'),
                    number_format((float) $rfq->budget, 2, ',', '.'),
                    $rfq->order_number,
                    $order->number,
                ),
                $rfq,
            );

            $message = sprintf(
                'Gegund aan %s voor EUR %s. Dat is EUR %s %s de begroting. Inkooporder %s met fotoplicht staat klaar en telt als verplichting in de prognose.',
                $subcontractor->name,
                number_format((float) $bid->price, 2, ',', '.'),
                number_format(abs($savings ?? 0), 2, ',', '.'),
                ($savings ?? 0) >= 0 ? 'onder' : 'boven',
                $order->number,
            );

            if ($margin !== null) {
                $message .= ' Marge '.Margin::format($margin).'.';

                if ($margin < Margin::baseline()) {
                    $message .= $approval !== null
                        ? ' Onder de bodem, vastgelegd met akkoord van '.$approval['door'].'.'
                        : ' Let op: dat is onder de '.Margin::format(Margin::baseline()).' waar we op sturen.';
                }
            }

            return $message;
        });
    }
}
