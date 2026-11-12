<?php

namespace App\Http\Controllers;

use App\Enums\TimelineEventType;
use App\Http\Requests\StoreLeadContactRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Contactmomenten op een aanvraag (briefing §4): elk gesprek, mailtje of
 * bezoek komt in het dossier en de opvolging wordt direct opnieuw gepland.
 * Ook voor de app (api/app): die krijgt JSON in plaats van een redirect.
 */
class LeadContactController extends Controller
{
    public function store(StoreLeadContactRequest $request, Lead $lead): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $type = match ($validated['type']) {
            'telefoon' => TimelineEventType::Telefoon,
            'email' => TimelineEventType::Email,
            'whatsapp' => TimelineEventType::Whatsapp,
            'bezoek' => TimelineEventType::Afspraak,
        };

        DB::transaction(fn () => $lead->logContact(
            $type,
            $validated['summary'],
            $validated['next_action'] ?? null,
            $validated['next_action_at'] ?? null,
        ));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Contactmoment vastgelegd.'], 201);
        }

        return redirect()
            ->route('leads.show', $lead)
            ->with('success', 'Contactmoment vastgelegd.');
    }
}
