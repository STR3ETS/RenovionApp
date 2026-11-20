<?php

namespace App\Http\Controllers;

use App\Enums\CallOutcome;
use App\Enums\NextActionType;
use App\Enums\TimelineEventType;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;

/**
 * Een belpoging vastleggen, vanuit de agenda of vanaf de aanvraag. De uitkomst
 * bepaalt wat er daarna gebeurt:
 *
 * - gesproken: contact vastgelegd, eventueel met een vervolgstap
 * - geen gehoor: opnieuw over twee uur of morgen om tien uur
 * - voicemail: ingesproken, eventueel met een mail erachteraan, morgen opnieuw
 * - terugbelverzoek: de klant wil op een afgesproken moment gebeld worden
 *
 * De volgende poging komt vanzelf in de agenda. Na twee keer niet bereikt
 * raadt Nova een ander kanaal aan.
 */
class CallAttemptController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_id' => ['required', 'exists:leads,id'],
            'outcome' => ['required', new Enum(CallOutcome::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            // Bij geen gehoor of voicemail: over twee uur of morgen.
            'retry' => ['nullable', 'in:plus2,morgen'],
            'mail_sent' => ['nullable', 'boolean'],
            // Bij een terugbelverzoek, of een vervolgstap na een gesprek.
            'next_date' => ['nullable', 'date', 'required_if:outcome,terugbelverzoek'],
            'next_time' => ['nullable', 'date_format:H:i'],
            'next_type' => ['nullable', new Enum(NextActionType::class)],
        ], [
            'next_date.required_if' => 'Kies wanneer de klant gebeld wil worden.',
        ]);

        $lead = Lead::with('customer')->findOrFail($validated['lead_id']);
        $outcome = CallOutcome::from($validated['outcome']);

        $nextAt = match (true) {
            $outcome->isMissed() => Lead::retryMoment($validated['retry'] ?? 'morgen'),
            filled($validated['next_date'] ?? null) => Carbon::parse($validated['next_date'].' '.($validated['next_time'] ?? '10:00')),
            default => null,
        };

        DB::transaction(function () use ($lead, $outcome, $validated, $nextAt, $request) {
            $lead->logCallAttempt(
                $outcome,
                $validated['note'] ?? null,
                $nextAt,
                isset($validated['next_type']) ? NextActionType::from($validated['next_type']) : null,
                $request->boolean('mail_sent'),
            );

            // Zonder eigenaar staat de volgende poging bij niemand in de agenda.
            if ($lead->assigned_to === null) {
                $lead->update(['assigned_to' => $request->user()->id]);
            }

            // De mail na een voicemail is een eigen contactmoment in het dossier.
            if ($outcome === CallOutcome::Voicemail && $request->boolean('mail_sent')) {
                $lead->customer->recordEvent(
                    TimelineEventType::Email,
                    'Mail gestuurd na voicemail',
                    null,
                    $lead,
                );
            }
        });

        $lead->refresh();

        $message = match (true) {
            $outcome === CallOutcome::Gesproken && $nextAt === null => 'Gesprek vastgelegd.',
            $outcome === CallOutcome::Gesproken => 'Gesprek vastgelegd, vervolg staat in de agenda op '.$nextAt->translatedFormat('j M H:i').'.',
            $outcome === CallOutcome::Terugbelverzoek => 'Terugbellen staat in de agenda op '.$nextAt->translatedFormat('j M H:i').'.',
            default => $outcome->label().'. Volgende poging staat in de agenda op '.$nextAt->translatedFormat('j M H:i').'.',
        };

        if ($advice = $lead->callAdvice()) {
            return back()->with('error', $message.' Nova: '.$advice);
        }

        return back()->with('success', $message);
    }
}
