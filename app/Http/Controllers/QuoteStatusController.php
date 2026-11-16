<?php

namespace App\Http\Controllers;

use App\Actions\AcceptQuote;
use App\Enums\QuoteStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuoteStatusController extends Controller
{
    public function update(Request $request, Quote $quote, AcceptQuote $acceptQuote): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(QuoteStatus::class)],
        ]);

        $old = $quote->status;
        $new = QuoteStatus::from($validated['status']);

        if (! $quote->status->isOpen()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Deze offerte is al afgehandeld.'], 422)
                : back()->with('error', 'Deze offerte is al afgehandeld.');
        }

        if ($new === QuoteStatus::Akkoord) {
            $project = $acceptQuote->handle($quote);

            // De app gaat zelf naar het nieuwe project.
            if ($request->expectsJson()) {
                return response()->json(['status' => $new->value, 'label' => $new->label(), 'project' => route('projects.show', $project, false)]);
            }

            return redirect()->route('projects.show', $project)
                ->with('success', "Offerte akkoord - project \"{$project->name}\" aangemaakt.");
        }

        if ($new === QuoteStatus::Verstuurd) {
            $quote->forceFill([
                'status' => $new,
                'sent_at' => $quote->sent_at ?? now(),
            ])->save();

            // Bij versturen wordt de huidige inhoud bevroren als versie (briefing §6).
            $quote->freezeVersion();
        }

        match ($new) {
            QuoteStatus::Verstuurd => null,
            QuoteStatus::Bekeken => $quote->forceFill([
                'status' => $new,
                'viewed_at' => now(),
                'viewed_count' => $quote->viewed_count + 1,
            ])->save(),
            QuoteStatus::Afgewezen => $quote->forceFill([
                'status' => $new,
                'rejected_at' => now(),
            ])->save(),
            default => $quote->forceFill(['status' => $new])->save(),
        };

        AuditLog::record($quote, 'status_gewijzigd', ['status' => $old->value], ['status' => $new->value]);

        if ($new === QuoteStatus::Verstuurd) {
            $quote->customer->recordEvent(TimelineEventType::Offerte, "Offerte {$quote->number} verstuurd", null, $quote);
        }

        if ($new === QuoteStatus::Afgewezen) {
            $quote->customer->recordEvent(TimelineEventType::Offerte, "Offerte {$quote->number} afgewezen", null, $quote);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => $new->value, 'label' => $new->label()]);
        }

        return back()->with('success', "Offertestatus gewijzigd naar {$new->label()}.");
    }
}
