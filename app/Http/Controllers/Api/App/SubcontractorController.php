<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Rfq;
use App\Models\Subcontractor;
use App\Support\App\Badges;
use App\Support\CertificateCatalogue;
use Illuminate\Http\JsonResponse;

/**
 * Onderaannemers in de app (subcontractors/index en subcontractors/show). De lijst komt in één keer, zodat
 * zoeken en filteren op vak en status op de telefoon zelf gaan (ook zonder verbinding), zoals bij Klanten.
 * Aanmelden, gegevens, dossierstukken en het partnerportaal via de SubcontractorController van de website.
 */
class SubcontractorController extends Controller
{
    /** @var array<string, string> Dezelfde tabs als de website, in dezelfde volgorde. */
    private const STATUSES = [
        Subcontractor::STATUS_APPROVED => 'Goedgekeurd',
        Subcontractor::STATUS_INCOMPLETE => 'Dossier incompleet',
        Subcontractor::STATUS_EXPIRED => 'Document verlopen',
        Subcontractor::STATUS_BLOCKED => 'Geblokkeerd',
    ];

    public function index(): JsonResponse
    {
        $subcontractors = Subcontractor::with(['documents', 'certificates'])->orderBy('name')->get();

        return response()->json([
            'statuses' => collect(self::STATUSES)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'trades' => CertificateCatalogue::TRADES,
            'subcontractors' => $subcontractors->map(function (Subcontractor $sub) {
                $dossier = $sub->dossier();
                $status = $sub->status();
                $open = count($dossier['open']);

                return [
                    'id' => $sub->id,
                    'name' => $sub->name,
                    'city' => $sub->city,
                    'contact' => collect([$sub->contact_name, $sub->city])->filter()->join(', ') ?: 'geen contactgegevens',
                    'trades' => array_values($sub->trades ?? []),
                    'status' => ['value' => $status, 'label' => $sub->statusLabel(), 'classes' => Badges::subcontractor($status)],
                    'dossier' => $dossier['complete'] ? 'Dossier rond' : $open.' '.($open === 1 ? 'punt' : 'punten').' open',
                    'complete' => $dossier['complete'],
                    'meta' => $sub->assignments.' '.($sub->assignments === 1 ? 'opdracht' : 'opdrachten').($sub->rating ? ", {$sub->rating} van 5" : ''),
                    'path' => route('subcontractors.show', $sub, false),
                ];
            }),
        ]);
    }

    public function show(Subcontractor $subcontractor): JsonResponse
    {
        $subcontractor->load(['documents', 'certificates', 'awardedRfqs.project', 'awardedRfqs.bids', 'bids.rfq', 'user']);
        $dossier = $subcontractor->dossier();
        $status = $subcontractor->status();

        return response()->json([
            'id' => $subcontractor->id,
            'name' => $subcontractor->name,
            'subtitle' => collect([
                $subcontractor->contact_name,
                $subcontractor->city,
                $subcontractor->coc_number ? 'KvK '.$subcontractor->coc_number : null,
            ])->filter()->join(', '),
            'status' => ['value' => $status, 'label' => $subcontractor->statusLabel(), 'classes' => Badges::subcontractor($status)],
            'blocked' => $subcontractor->blocked ? ($subcontractor->blocked_reason ?: 'Geen reden vastgelegd.') : null,
            'phone' => $subcontractor->phone,
            'email' => $subcontractor->email,
            'dossier' => [
                'complete' => $dossier['complete'],
                'open' => count($dossier['open']),
                'items' => array_map(fn (array $item) => [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'ok' => $item['state'] === 'ok',
                    'classes' => Badges::state($item['state']),
                    'sub' => CertificateCatalogue::stateLabel($item['state'])
                        .($item['until'] ? ', geldig tot '.$item['until']->translatedFormat('j M Y') : ''),
                ], $dossier['items']),
            ],
            'document_types' => array_keys(CertificateCatalogue::PARTNER_DOCUMENTS),
            'agreement_types' => array_keys(CertificateCatalogue::PARTNER_AGREEMENTS),
            'awarded' => $subcontractor->awardedRfqs->map(fn (Rfq $rfq) => [
                'id' => $rfq->id,
                'title' => $rfq->title,
                'sub' => collect([$rfq->order_number, $rfq->project?->name])->filter()->join(', '),
                'price' => (float) ($rfq->winningBid()?->price ?? 0),
                'path' => route('rfqs.show', $rfq, false),
            ]),
            'form' => [
                'contact_name' => $subcontractor->contact_name,
                'email' => $subcontractor->email,
                'phone' => $subcontractor->phone,
                'coc_number' => $subcontractor->coc_number,
                'hourly_rate' => $subcontractor->hourly_rate !== null ? (float) $subcontractor->hourly_rate : null,
                'rating' => $subcontractor->rating,
                'trades' => array_values($subcontractor->trades ?? []),
                'id_checked' => $subcontractor->id_checked,
                'blocked' => $subcontractor->blocked,
                'blocked_reason' => $subcontractor->blocked_reason,
            ],
            'trades' => CertificateCatalogue::TRADES,
            'portal' => $subcontractor->user?->email,
            'links' => ['web' => route('subcontractors.show', $subcontractor, false)],
        ]);
    }
}
