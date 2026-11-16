<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteVersion;
use Illuminate\Http\JsonResponse;

/**
 * Een offerte in de app (quotes/show): signalen, klantlink, posten, versies, verloop en de statusknoppen.
 * Status wijzigen en een nieuwe versie gaan via dezelfde controllers als de website; het document zelf
 * (zoals de klant het ziet) is een eigen scherm in de app (document()), met PDF via de printdialoog.
 */
class QuoteController extends Controller
{
    public function show(Quote $quote): JsonResponse
    {
        $quote->load(['customer', 'lines', 'versions.creator', 'calculation', 'project', 'lead']);
        $customer = $quote->customer;
        $concept = $quote->status === QuoteStatus::Concept;
        $daysOpen = $quote->daysOpen();
        $euro = fn ($amount) => (float) $amount;

        $signals = array_values(array_filter([
            $quote->change_request ? ['color' => 'amber', 'text' => 'De klant vraagt een aanpassing: '.$quote->change_request] : null,
            $daysOpen !== null && $daysOpen >= 3 ? [
                'color' => $daysOpen >= 5 ? 'red' : 'amber',
                'text' => "Staat {$daysOpen} dagen open".($quote->viewed_count > 0 ? ", {$quote->viewed_count}× bekeken" : '').': vandaag opvolgen',
            ] : null,
            $quote->isExpired() && $quote->status->isOpen() ? ['color' => 'red', 'text' => 'Geldigheid verlopen op '.$quote->valid_until->translatedFormat('j M Y')] : null,
        ]));

        // Dezelfde knoppen als de website: een concept versturen, een verstuurde offerte opvolgen; akkoord of afgewezen kan altijd zolang hij open is.
        $actions = $quote->status->isOpen()
            ? array_map(fn (QuoteStatus $status) => ['value' => $status->value, 'label' => match ($status) {
                QuoteStatus::Verstuurd => 'Versturen',
                QuoteStatus::Opvolgen => 'Opvolgen',
                QuoteStatus::Akkoord => 'Akkoord, maak project',
                default => $status->label(),
            }], [$concept ? QuoteStatus::Verstuurd : QuoteStatus::Opvolgen, QuoteStatus::Akkoord, QuoteStatus::Afgewezen])
            : [];

        return response()->json([
            'id' => $quote->id,
            'number' => $quote->number,
            'version' => $quote->version,
            'title' => $quote->lead?->service,
            'subtitle' => $customer->name.', aangemaakt '.$quote->created_at->translatedFormat('j M Y'),
            'status' => ['value' => $quote->status->value, 'label' => $quote->status->label(), 'classes' => $quote->status->appBadgeClasses()],
            'concept' => $concept,
            'open' => $quote->status->isOpen(),
            'signals' => $signals,
            'actions' => $actions,
            'public_url' => $concept ? null : $quote->publicUrl(),
            'customer' => [
                'name' => $customer->name,
                'city' => $customer->city,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'path' => route('customers.show', $customer, false),
            ],
            'mail' => ! $concept && $customer->email ? 'mailto:'.$customer->email
                .'?subject='.rawurlencode('Offerte '.$quote->number.' - Renovion')
                .'&body='.rawurlencode("Beste {$customer->name},\n\nHierbij ontvangt u onze offerte. U kunt hem bekijken en digitaal ondertekenen via:\n{$quote->publicUrl()}\n\nMet vriendelijke groet,\nRenovion") : null,
            'lines' => $quote->lines->map(fn (QuoteLine $line) => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => rtrim(rtrim(number_format((float) $line->quantity, 2, ',', '.'), '0'), ',').' '.$line->unit,
                'total' => $euro($line->total),
                'estimate' => (bool) $line->is_estimate,
            ]),
            'subtotal' => $euro($quote->subtotal),
            'vat' => $euro($quote->vat_amount),
            'total' => $euro($quote->total),
            'versions' => $quote->versions
                ->reject(fn (QuoteVersion $version) => $version->version === $quote->version)
                ->sortByDesc('version')
                ->values()
                ->map(fn (QuoteVersion $version) => [
                    'version' => $version->version,
                    'note' => $version->note ?? 'Verstuurde versie',
                    'sub' => $version->created_at->translatedFormat('j M Y H:i').($version->creator ? ', '.$version->creator->name : ''),
                    'total' => $euro($version->snapshot['total'] ?? 0),
                ]),
            'notes' => $quote->notes,
            'signed' => $quote->signed_at ? [
                'name' => $quote->signed_name,
                'sub' => $quote->signed_at->translatedFormat('j M Y H:i').', versie v'.$quote->version.', IP '.$quote->signed_ip,
            ] : null,
            'history' => array_values(array_filter([
                ['label' => 'Aangemaakt', 'value' => $quote->created_at->translatedFormat('j M Y H:i')],
                ['label' => 'Verstuurd', 'value' => $quote->sent_at?->translatedFormat('j M Y H:i') ?? '-'],
                ['label' => 'Laatst bekeken', 'value' => ($quote->viewed_at?->translatedFormat('j M Y H:i') ?? '-').($quote->viewed_count > 0 ? " ({$quote->viewed_count}×)" : '')],
                ['label' => 'Geldig tot', 'value' => $quote->valid_until?->translatedFormat('j M Y') ?? '-', 'late' => $quote->isExpired() && $quote->status->isOpen()],
                $quote->accepted_at ? ['label' => 'Akkoord op', 'value' => $quote->accepted_at->translatedFormat('j M Y H:i')] : null,
                $quote->rejected_at ? ['label' => 'Afgewezen op', 'value' => $quote->rejected_at->translatedFormat('j M Y H:i')] : null,
            ])),
            // Na akkoord hoort de offerte bij een project: daar kun je naar terug (kruimelpad).
            'project' => $quote->project ? ['name' => $quote->project->name, 'path' => route('projects.show', $quote->project, false)] : null,
            'links' => [
                'web' => route('quotes.show', $quote, false),
                'document' => route('quotes.document', $quote, false),
                'builder' => $concept ? route('quotes.builder', $quote, false) : null,
                'edit' => $concept ? route('quotes.edit', $quote, false) : null,
                'calculation' => $quote->calculation ? ['title' => $quote->calculation->title, 'path' => route('calculations.show', $quote->calculation, false)] : null,
                'project' => $quote->project ? route('projects.show', $quote->project, false) : null,
                'lead' => $quote->lead ? route('leads.show', $quote->lead, false) : null,
            ],
        ]);
    }

    /**
     * Het document zoals de klant het ziet, voor het documentscherm in de app (screens/offertedocument.*).
     * De blokken komen uit dezelfde Blade-weergaven als de klantpagina (quotes.public), dus het blijft gelijk;
     * de app toont ze in zijn eigen scherm en maakt de PDF via de printdialoog. Telt niet als bekeken.
     */
    public function document(Quote $quote): JsonResponse
    {
        $quote->load(['lines', 'customer', 'project']);

        $banner = match (true) {
            $quote->status === QuoteStatus::Akkoord => ['tone' => 'ok', 'text' => 'Ondertekend door '.($quote->signed_name ?? $quote->customer->name)
                .' op '.($quote->signed_at ?? $quote->accepted_at)?->translatedFormat('j F Y')],
            $quote->status === QuoteStatus::Afgewezen => ['tone' => 'off', 'text' => 'Deze offerte is afgewezen.'],
            $quote->isExpired() => ['tone' => 'late', 'text' => 'De geldigheidsdatum is verstreken.'],
            default => null,
        };

        return response()->json([
            'id' => $quote->id,
            'number' => $quote->number,
            'version' => $quote->version,
            'title' => $quote->lead?->service ?? 'Offerte '.$quote->number,
            'customer' => $quote->customer->name,
            'concept' => $quote->status === QuoteStatus::Concept,
            'banner' => $banner,
            'html' => view('quotes.partials.document', ['quote' => $quote])->render()
                .view('quotes.partials.footer', ['links' => false])->render(),
            'path' => route('quotes.show', $quote, false),
            'project' => $quote->project ? ['name' => $quote->project->name, 'path' => route('projects.show', $quote->project, false)] : null,
        ]);
    }
}
