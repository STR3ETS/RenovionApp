<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FollowUpController as WebFollowUpController;
use Illuminate\Http\JsonResponse;

/**
 * Opvolging in de app (follow-ups/index): dezelfde rijen en volgorde als de website (FollowUpController::rows()).
 * Alles in één keer; het filter op staat gebeurt op de telefoon, dus ook zonder verbinding.
 */
class FollowUpController extends Controller
{
    public function index(WebFollowUpController $followUps): JsonResponse
    {
        $rows = $followUps->rows();
        $counts = $rows->countBy('key');

        return response()->json([
            'total' => $rows->count(),
            'filters' => collect(WebFollowUpController::LABELS)
                ->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label, 'count' => $counts[$key] ?? 0])
                ->values(),
            'rows' => $rows->map(function (array $row) {
                $lead = $row['lead'];

                return [
                    'id' => $lead->id,
                    'name' => $lead->customer?->name ?? 'Onbekende klant',
                    'status' => ['label' => $lead->status->label(), 'classes' => $lead->status->appBadgeClasses()],
                    'silent' => $row['silent'],
                    'sub' => ($lead->service ?: 'geen dienst').', '
                        .($lead->last_contact_at ? 'laatste contact '.$lead->last_contact_at->diffForHumans() : 'nog geen contact geweest')
                        .($lead->assignee ? ', '.$lead->assignee->name : ''),
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'next' => $lead->next_action_at
                        ? ($lead->next_action ?: 'vervolgstap').', '.$lead->next_action_at->translatedFormat('j M')
                        : 'zet een vervolgstap',
                    'phone' => $lead->customer?->phone,
                    'path' => route('leads.show', $lead, false),
                ];
            }),
        ]);
    }
}
