<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Opvolging: alle open aanvragen op een rij, gesorteerd op hoe dringend ze zijn.
 *
 * De volgorde is bewust: te laat bovenaan, dan wat niets gepland heeft, dan
 * vandaag, dan later. Een aanvraag waar niemand een vervolgstap op heeft gezet
 * is gevaarlijker dan een aanvraag die volgende week aan de beurt is, want die
 * laatste wordt tenminste nog gebeld.
 */
class FollowUpController extends Controller
{
    /** Na zoveel dagen zonder contact is een aanvraag stil gevallen. */
    private const SILENT_AFTER_DAYS = 5;

    public function index(Request $request): View
    {
        $leads = Lead::open()
            ->with(['customer', 'assignee', 'intake'])
            ->get()
            ->map(fn (Lead $lead) => ['lead' => $lead, ...$this->state($lead)])
            ->sortBy([
                fn (array $a, array $b) => $a['rank'] <=> $b['rank'],
                fn (array $a, array $b) => ($a['lead']->next_action_at ?? now()->addYear())
                    <=> ($b['lead']->next_action_at ?? now()->addYear()),
            ])
            ->values();

        $filter = $request->string('filter')->toString();

        return view('follow-ups.index', [
            'rows' => $filter !== '' ? $leads->where('key', $filter)->values() : $leads,
            'counts' => $leads->countBy('key'),
            'total' => $leads->count(),
            'filter' => $filter,
            'labels' => [
                'laat' => 'Te laat',
                'niets' => 'Niets gepland',
                'vandaag' => 'Vandaag',
                'later' => 'Later',
            ],
        ]);
    }

    /**
     * De staat van de opvolging. Vier mogelijkheden, met een rangnummer dat de
     * sortering bepaalt.
     *
     * @return array{key: string, label: string, rank: int, silent: bool}
     */
    private function state(Lead $lead): array
    {
        $silent = $lead->last_contact_at === null
            || $lead->last_contact_at->diffInDays(now()) >= self::SILENT_AFTER_DAYS;

        if ($lead->next_action_at === null) {
            return ['key' => 'niets', 'label' => 'Niets gepland', 'rank' => 1, 'silent' => $silent];
        }

        if ($lead->next_action_at->isPast() && ! $lead->next_action_at->isToday()) {
            return ['key' => 'laat', 'label' => 'Te laat', 'rank' => 0, 'silent' => $silent];
        }

        if ($lead->next_action_at->isToday()) {
            return ['key' => 'vandaag', 'label' => 'Vandaag', 'rank' => 2, 'silent' => $silent];
        }

        return ['key' => 'later', 'label' => 'Later', 'rank' => 3, 'silent' => $silent];
    }
}
