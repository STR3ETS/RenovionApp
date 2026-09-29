<?php

namespace App\Http\Controllers;

use App\Actions\GenerateDeliveryReport;
use App\Models\AuditLog;
use App\Models\DeliveryReport;
use App\Models\Photo;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryReportController extends Controller
{
    public function store(Request $request, Project $project, GenerateDeliveryReport $action): RedirectResponse
    {
        if ($project->deliveryReport?->isSignedByClient()) {
            return redirect()->route('delivery-reports.show', $project->deliveryReport)
                ->with('error', 'De klant heeft dit rapport al ondertekend — het kan niet meer worden hergenereerd.');
        }

        $report = $action->handle($project, $request->user());

        return redirect()->route('delivery-reports.show', $report)
            ->with('success', 'Opleverrapport '.($report->wasRecentlyCreated ? 'aangemaakt' : 'bijgewerkt').' op basis van de actuele projectdata.');
    }

    public function show(Request $request, DeliveryReport $deliveryReport): View
    {
        abort_unless($deliveryReport->project->isAccessibleBy($request->user()), 403);

        $deliveryReport->load(['project.customer', 'generator', 'companySigner']);

        return view('delivery-reports.show', [
            'report' => $deliveryReport,
            'photos' => self::photos($deliveryReport),
        ]);
    }

    /**
     * Handtekening namens Renovion (briefing §13).
     */
    public function sign(Request $request, DeliveryReport $deliveryReport): RedirectResponse
    {
        if ($deliveryReport->company_signed_at !== null) {
            return back()->with('error', 'Dit rapport is al namens Renovion ondertekend.');
        }

        $deliveryReport->update([
            'company_signed_by' => $request->user()->id,
            'company_signed_at' => now(),
        ]);

        AuditLog::record($deliveryReport, 'ondertekend_renovion', [], ['door' => $request->user()->name]);

        return back()->with('success', 'Rapport ondertekend namens Renovion.');
    }

    /**
     * Alle foto's uit de snapshot, geladen op id (gedeeld met het portaal).
     *
     * @return Collection<int, Photo>
     */
    public static function photos(DeliveryReport $report)
    {
        $ids = collect($report->snapshot['work'] ?? [])
            ->flatMap(fn (array $package) => $package['photo_ids'] ?? [])
            ->concat($report->snapshot['general_photo_ids'] ?? [])
            ->unique();

        return Photo::whereIn('id', $ids)->where('client_visible', true)->get()->keyBy('id');
    }
}
