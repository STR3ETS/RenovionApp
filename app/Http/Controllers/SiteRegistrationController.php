<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectAgreement;
use App\Models\SiteRegistration;
use App\Models\Subcontractor;
use App\Models\SubcontractorFramework;
use App\Models\SubcontractorLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Aanmelding van de bouw en de overeenkomsten met onderaannemers: wie is waar
 * aangemeld, is het mailpakket verstuurd, en ligt per onderaannemer de
 * overeenkomst voor dit project vast.
 */
class SiteRegistrationController extends Controller
{
    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'gpi_registered_on' => ['nullable', 'date'],
            'office_registered_on' => ['nullable', 'date'],
            'site_registered_on' => ['nullable', 'date'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $registration = SiteRegistration::firstOrNew(['project_id' => $project->id]);
        $old = $registration->only(array_keys($validated));
        $registration->fill($validated)->save();

        AuditLog::record($registration, 'aanmelding bouw bijgewerkt', $old, $validated);

        return back()->with('success', 'Aanmelding bijgewerkt.');
    }

    /** Vastleggen dat het mailpakket met alle documenten de deur uit is. */
    public function packageSent(Request $request, Project $project): RedirectResponse
    {
        $registration = SiteRegistration::firstOrCreate(['project_id' => $project->id]);

        $registration->update(['package_sent_at' => now(), 'package_sent_by' => $request->user()->id]);

        AuditLog::record($registration, 'mailpakket aanmelding verstuurd', [], ['door' => $request->user()->name]);

        return back()->with('success', 'Vastgelegd dat het mailpakket is verstuurd.');
    }

    /** De getekende overeenkomst met een onderaannemer voor dit project is binnen. */
    public function recordAgreement(Request $request, ProjectAgreement $agreement): RedirectResponse
    {
        if ($agreement->isRecorded()) {
            return back()->with('error', 'Deze overeenkomst is al vastgelegd.');
        }

        $agreement->update(['recorded_at' => now(), 'recorded_by' => $request->user()->id]);

        SubcontractorLog::note(
            $agreement->subcontractor,
            'Overeenkomst vastgelegd voor '.$agreement->project->name.($agreement->rfq ? ', '.$agreement->rfq->number : ''),
            $agreement->project_id,
            $request->user(),
        );

        AuditLog::record($agreement, 'overeenkomst vastgelegd', [], ['partner' => $agreement->subcontractor->name]);

        return back()->with('success', 'Overeenkomst met '.$agreement->subcontractor->name.' vastgelegd.');
    }

    /**
     * Een nieuwe periode van zes maanden voor doorlopend werk. Dat gebeurt
     * nooit vanzelf: iemand legt de nieuwe periode bewust vast.
     */
    public function storeFramework(Request $request, Subcontractor $subcontractor): RedirectResponse
    {
        $validated = $request->validate(['starts_on' => ['nullable', 'date']]);

        $subcontractor->loadMissing('frameworks');
        $running = $subcontractor->activeFramework();

        // Een nieuwe periode sluit aan op de lopende, of begint vandaag.
        $start = filled($validated['starts_on'] ?? null)
            ? $request->date('starts_on')
            : ($running?->ends_on->copy()->addDay() ?? today());

        $framework = $subcontractor->frameworks()->create([
            'starts_on' => $start,
            'ends_on' => $start->copy()->addMonths(SubcontractorFramework::MONTHS)->subDay(),
            'recorded_by' => $request->user()->id,
        ]);

        SubcontractorLog::note(
            $subcontractor,
            'Raamovereenkomst vastgelegd van '.$framework->starts_on->translatedFormat('j M Y').' t/m '.$framework->ends_on->translatedFormat('j M Y'),
            null,
            $request->user(),
        );

        AuditLog::record($subcontractor, 'raamovereenkomst onderaannemer vastgelegd', [], [
            'van' => $framework->starts_on->toDateString(),
            'tot' => $framework->ends_on->toDateString(),
        ]);

        return back()->with('success', 'Nieuwe periode vastgelegd tot en met '.$framework->ends_on->translatedFormat('j F Y').'.');
    }
}
