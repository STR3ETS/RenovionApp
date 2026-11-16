<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DeliveryReportController as WebDeliveryReportController;
use App\Models\DeliveryReport;
use App\Models\Photo;
use App\Support\App\FieldWork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Het opleverrapport in de app (delivery-reports/show), uit de bevroren snapshot, met dezelfde foto's als
 * de website en het portaal. Hergenereren en ondertekenen namens Renovion (projectleiding) gaan via de
 * DeliveryReportController van de website; PDF downloaden opent de serverpagina (printdialoog).
 */
class DeliveryReportController extends Controller
{
    public function show(Request $request, DeliveryReport $deliveryReport): JsonResponse
    {
        abort_unless($deliveryReport->project->isAccessibleBy($request->user()), 403);

        $deliveryReport->load(['project.customer', 'generator', 'companySigner']);
        $snapshot = $deliveryReport->snapshot;
        $info = $snapshot['project'] ?? [];
        $photos = WebDeliveryReportController::photos($deliveryReport);
        $date = fn (?string $value, string $format = 'j M Y') => $value ? Carbon::parse($value)->translatedFormat($format) : null;
        $pick = fn (iterable $ids, string $fallback) => collect($ids)->map(fn ($id) => $photos->get($id))->filter()
            ->map(fn (Photo $photo) => FieldWork::photo($photo, $fallback))->values();
        $deviation = $info['deviation_days'] ?? null;
        $crm = $request->user()->can('manage-crm');

        return response()->json([
            'id' => $deliveryReport->id,
            'project' => [
                'id' => $deliveryReport->project->id,
                'name' => $deliveryReport->project->name,
                'path' => route('projects.show', $deliveryReport->project, false),
            ],
            'subtitle' => $deliveryReport->project->name.', '.$deliveryReport->project->customer->name,
            'fully_signed' => $deliveryReport->isFullySigned(),
            'client_signed' => $deliveryReport->isSignedByClient(),
            'can_regenerate' => $crm && ! $deliveryReport->isSignedByClient(),
            'can_sign' => $crm && $deliveryReport->company_signed_at === null,
            'header' => [
                'name' => $info['name'] ?? $deliveryReport->project->name,
                'sub' => collect([
                    $info['customer'] ?? null,
                    filled($info['address'] ?? null) ? $info['address'] : null,
                    ($info['quote_number'] ?? null) ? 'offerte '.$info['quote_number'] : null,
                ])->filter()->join(', '),
                'generated' => 'Opgesteld op '.$deliveryReport->generated_at->translatedFormat('j F Y').($deliveryReport->generator ? ' door '.$deliveryReport->generator->name : ''),
            ],
            'planning' => [
                ['label' => 'Gestart', 'value' => $date($info['start_date'] ?? null) ?? '-', 'tone' => null],
                ['label' => 'Verwacht', 'value' => $date($info['end_date_expected'] ?? null) ?? '-', 'tone' => null],
                ['label' => 'Opgeleverd', 'value' => $date($info['end_date_actual'] ?? null) ?? '-', 'tone' => null],
                ['label' => 'Afwijking', 'value' => $deviation === null ? '-' : ($deviation > 0 ? "+{$deviation} dagen" : 'Op schema'), 'tone' => $deviation === null ? null : ($deviation > 0 ? 'amber' : 'green')],
            ],
            'scope' => filled($info['scope'] ?? null) ? $info['scope'] : null,
            'phases' => collect($snapshot['phases'] ?? [])->map(fn (array $phase) => [
                'name' => $phase['name'],
                'sub' => collect([
                    ($phase['approved_by'] ?? null) ? 'afgerond door '.$phase['approved_by'] : null,
                    ($phase['client_approved_at'] ?? null) ? 'klant akkoord '.$date($phase['client_approved_at'], 'j M') : null,
                ])->filter()->join(', '),
                'status' => $phase['status'],
                'done' => (bool) $phase['done'],
            ])->values(),
            'work' => collect($snapshot['work'] ?? [])->map(fn (array $package) => [
                'name' => $package['name'],
                'sub' => $package['phase'].(($package['responsible'] ?? null) ? ', '.$package['responsible'] : ''),
                'status' => $package['status'],
                'done' => (bool) $package['done'],
                'items' => collect($package['items'] ?? [])->map(fn (array $item) => [
                    'label' => $item['label'],
                    'meta' => collect([$item['done_by'] ?? null, $date($item['done_at'] ?? null, 'j M')])->filter()->join(', '),
                ])->values(),
                'photos' => $pick($package['photo_ids'] ?? [], 'Bewijsfoto'),
            ])->values(),
            'general_photos' => $pick($snapshot['general_photo_ids'] ?? [], 'Projectfoto'),
            'changes' => collect($snapshot['changes'] ?? [])->map(fn (array $change) => [
                'version' => $change['version'],
                'note' => $change['note'],
                'date' => $date($change['date'] ?? null),
            ])->values(),
            'leftovers' => $this->leftovers($snapshot['leftovers'] ?? [], $date),
            'warranty' => $snapshot['warranty'] ?? null,
            'signatures' => [
                'company' => $deliveryReport->company_signed_at ? [
                    'name' => $deliveryReport->companySigner?->name ?? 'Renovion',
                    'at' => $deliveryReport->company_signed_at->translatedFormat('j F Y H:i'),
                ] : null,
                'client' => $deliveryReport->client_signed_at ? [
                    'name' => $deliveryReport->client_signed_name,
                    'at' => $deliveryReport->client_signed_at->translatedFormat('j F Y H:i').', IP '.$deliveryReport->client_signed_ip,
                ] : null,
            ],
            'path' => route('delivery-reports.show', $deliveryReport, false),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function leftovers(array $leftovers, \Closure $date): Collection
    {
        return collect($leftovers)->map(fn (array $item) => [
            'title' => $item['title'],
            'type' => $item['type'],
            'owner' => $item['owner'] ?? '-',
            'deadline' => $date($item['deadline'] ?? null) ?? '-',
            'status' => $item['status'],
        ])->values();
    }
}
