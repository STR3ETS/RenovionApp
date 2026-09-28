<?php

namespace App\Http\Controllers;

use App\Actions\AcceptQuote;
use App\Enums\ActionSource;
use App\Enums\QuoteStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Klantview van de offerte (briefing §6): bekijken via een privélink,
 * digitaal ondertekenen, aanpassing aanvragen of afwijzen — zonder inlog.
 */
class QuotePublicController extends Controller
{
    public function show(Quote $quote): View
    {
        abort_if($quote->status === QuoteStatus::Concept, 404);

        $quote->load(['lines', 'customer']);

        if ($quote->status->isOpen()) {
            $wasUnseen = $quote->status === QuoteStatus::Verstuurd;

            $quote->forceFill([
                'status' => $wasUnseen ? QuoteStatus::Bekeken : $quote->status,
                'viewed_at' => now(),
                'viewed_count' => $quote->viewed_count + 1,
            ])->save();

            if ($wasUnseen) {
                $quote->customer->recordEvent(
                    TimelineEventType::Offerte,
                    "Offerte {$quote->number} bekeken door de klant",
                    null,
                    $quote,
                    ActionSource::Website,
                );
            }
        }

        return view('quotes.public', ['quote' => $quote]);
    }

    public function sign(Request $request, Quote $quote, AcceptQuote $acceptQuote): RedirectResponse
    {
        $validated = $request->validate([
            'signed_name' => ['required', 'string', 'max:255'],
            'agree' => ['accepted'],
        ], [], ['signed_name' => 'naam', 'agree' => 'akkoordverklaring']);

        if (! $quote->status->isOpen() || $quote->status === QuoteStatus::Concept) {
            return back()->with('error', 'Deze offerte kan niet meer worden ondertekend.');
        }

        if ($quote->isExpired()) {
            return back()->with('error', 'De geldigheidsdatum van deze offerte is verstreken. Neem contact met ons op voor een actuele versie.');
        }

        $quote->forceFill([
            'signed_name' => $validated['signed_name'],
            'signed_at' => now(),
            'signed_ip' => $request->ip(),
        ])->save();

        AuditLog::record($quote, 'ondertekend', [], [
            'naam' => $validated['signed_name'],
            'ip' => (string) $request->ip(),
            'versie' => $quote->version,
        ], ActionSource::Website);

        $acceptQuote->handle($quote, ActionSource::Website);

        return redirect()->route('quotes.public', $quote->public_token)
            ->with('success', 'Bedankt voor uw akkoord! We nemen snel contact met u op over de planning.');
    }

    public function requestChange(Request $request, Quote $quote): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ], [], ['message' => 'toelichting']);

        if (! $quote->status->isOpen() || $quote->status === QuoteStatus::Concept) {
            return back()->with('error', 'Deze offerte is al afgehandeld.');
        }

        $quote->forceFill([
            'change_request' => $validated['message'],
            'status' => QuoteStatus::Opvolgen,
        ])->save();

        $quote->customer->recordEvent(
            TimelineEventType::Offerte,
            "Klant vraagt aanpassing op offerte {$quote->number}",
            $validated['message'],
            $quote,
            ActionSource::Website,
        );
        AuditLog::record($quote, 'aanpassing_gevraagd', [], ['toelichting' => $validated['message']], ActionSource::Website);

        return redirect()->route('quotes.public', $quote->public_token)
            ->with('success', 'Uw verzoek is ontvangen — we nemen snel contact met u op.');
    }

    public function reject(Request $request, Quote $quote): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ], [], ['reason' => 'reden']);

        if (! $quote->status->isOpen() || $quote->status === QuoteStatus::Concept) {
            return back()->with('error', 'Deze offerte is al afgehandeld.');
        }

        $quote->forceFill([
            'status' => QuoteStatus::Afgewezen,
            'rejected_at' => now(),
        ])->save();

        $quote->customer->recordEvent(
            TimelineEventType::Offerte,
            "Offerte {$quote->number} afgewezen door de klant",
            $validated['reason'] ?? null,
            $quote,
            ActionSource::Website,
        );
        AuditLog::record($quote, 'afgewezen', [], ['reden' => $validated['reason'] ?? '—'], ActionSource::Website);

        return redirect()->route('quotes.public', $quote->public_token)
            ->with('success', 'Bedankt voor uw reactie.');
    }
}
