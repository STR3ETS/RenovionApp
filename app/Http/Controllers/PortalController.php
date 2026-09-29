<?php

namespace App\Http\Controllers;

use App\Enums\ActionSource;
use App\Enums\PhaseStatus;
use App\Enums\TimelineEventType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DeliveryReport;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\ScheduleEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Klantportaal (briefing §10): vereenvoudigde tijdlijn, "deze week",
 * "actie van u nodig" en alleen klantzichtbare content. De klant ziet
 * nooit marges, interne notities of teamdiscussies.
 */
class PortalController extends Controller
{
    /**
     * Klantvriendelijke groepering van de interne fasen 0–8.
     *
     * @var array<string, list<int>>
     */
    private const TIMELINE = [
        'Voorbereiding' => [0, 1, 2, 3],
        'Uitvoering' => [4],
        'Controle & keuzes' => [5],
        'Vooroplevering' => [6],
        'Oplevering' => [7],
        'Nazorg' => [8],
    ];

    public function index(Request $request): View|RedirectResponse
    {
        $customer = $this->customerFor($request);

        $projects = $customer->projects()
            ->with(['phases.workPackages'])
            ->withCount(['photos' => fn ($query) => $query->where('client_visible', true)])
            ->latest()
            ->get();

        if ($projects->count() === 1) {
            return redirect()->route('portal.show', $projects->first());
        }

        return view('portal.index', [
            'customer' => $customer,
            'projects' => $projects,
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        abort_unless($project->isViewableByClient($request->user()), 403);

        $project->load(['customer', 'phases.workPackages', 'deliveryReport']);

        $photos = $project->photos()
            ->where('client_visible', true)
            ->with('phase')
            ->latest()
            ->limit(24)
            ->get();

        $dezeWeek = ScheduleEntry::where('project_id', $project->id)
            ->whereBetween('date', [today(), today()->addDays(7)])
            ->with('user')
            ->orderBy('date')
            ->orderByRaw('start_time is null, start_time asc')
            ->get();

        return view('portal.show', [
            'project' => $project,
            'timeline' => $this->timeline($project),
            'photos' => $photos,
            'dezeWeek' => $dezeWeek,
            'acties' => $this->clientActions($project),
            'opSchema' => ! $project->isOverdue()
                && $project->phases->doesntContain(fn (ProjectPhase $phase) => $phase->status === PhaseStatus::Geblokkeerd),
        ]);
    }

    /**
     * "Gezien en akkoord" bij een afgeronde fase (briefing §9): datum/tijd
     * en ondertekenaar worden vastgelegd.
     */
    public function approvePhase(Request $request, ProjectPhase $phase): RedirectResponse
    {
        abort_unless($phase->project->isViewableByClient($request->user()), 403);

        if ($phase->status !== PhaseStatus::Gereed || $phase->client_approved_at !== null) {
            return back()->with('error', 'Deze fase kan niet (meer) worden bevestigd.');
        }

        $phase->update([
            'client_approved_at' => now(),
            'client_approved_by' => $request->user()->id,
        ]);

        AuditLog::record($phase, 'klant_akkoord', [], [
            'fase' => $phase->name,
            'ondertekenaar' => $request->user()->name,
        ], ActionSource::Website);

        $phase->project->customer->recordEvent(
            TimelineEventType::Projectupdate,
            'Klant bevestigt "gezien en akkoord" voor fase: '.$phase->name,
            null,
            $phase,
            ActionSource::Website,
        );

        return back()->with('success', 'Bedankt voor uw bevestiging!');
    }

    /**
     * Opleverrapport in het portaal (briefing §13).
     */
    public function report(Request $request, DeliveryReport $report): View
    {
        abort_unless($report->project->isViewableByClient($request->user()), 403);

        $report->load(['project.customer', 'companySigner']);

        return view('portal.report', [
            'report' => $report,
            'photos' => DeliveryReportController::photos($report),
        ]);
    }

    public function signReport(Request $request, DeliveryReport $report): RedirectResponse
    {
        abort_unless($report->project->isViewableByClient($request->user()), 403);

        $validated = $request->validate([
            'signed_name' => ['required', 'string', 'max:255'],
            'agree' => ['accepted'],
        ], [], ['signed_name' => 'naam', 'agree' => 'akkoordverklaring']);

        if ($report->isSignedByClient()) {
            return back()->with('error', 'Dit rapport is al ondertekend.');
        }

        $report->update([
            'client_signed_name' => $validated['signed_name'],
            'client_signed_at' => now(),
            'client_signed_ip' => $request->ip(),
        ]);

        AuditLog::record($report, 'ondertekend_klant', [], [
            'naam' => $validated['signed_name'],
            'ip' => (string) $request->ip(),
        ], ActionSource::Website);

        $report->project->customer->recordEvent(
            TimelineEventType::Document,
            'Opleverrapport ondertekend door de klant',
            $validated['signed_name'],
            $report,
            ActionSource::Website,
        );

        return back()->with('success', 'Bedankt voor uw ondertekening — het rapport is definitief.');
    }

    private function customerFor(Request $request): Customer
    {
        abort_unless($request->user()->role === UserRole::Klant && $request->user()->customer_id !== null, 403);

        return $request->user()->customer;
    }

    /**
     * @return Collection<int, array{label: string, state: string}>
     */
    private function timeline(Project $project): Collection
    {
        return collect(self::TIMELINE)->map(function (array $positions, string $label) use ($project) {
            $phases = $project->phases->whereIn('position', $positions);

            $state = match (true) {
                $phases->every(fn (ProjectPhase $phase) => $phase->status === PhaseStatus::Gereed) => 'gereed',
                $phases->contains(fn (ProjectPhase $phase) => $phase->status !== PhaseStatus::NietGestart) => 'bezig',
                default => 'niet_gestart',
            };

            return ['label' => $label, 'state' => $state];
        })->values();
    }

    /**
     * "Actie van u nodig": wachten-op-klant-fasen, te bevestigen mijlpalen
     * en openstaande offertes.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function clientActions(Project $project): Collection
    {
        $acties = collect();

        foreach ($project->phases->where('status', PhaseStatus::WachtOpKlant) as $phase) {
            $acties->push([
                'type' => 'keuze',
                'title' => 'Uw keuze of reactie is nodig',
                'subtitle' => 'Fase: '.$phase->name.' — neem contact met ons op of reageer op ons laatste bericht.',
            ]);
        }

        foreach ($project->phases->filter(fn (ProjectPhase $phase) => $phase->status === PhaseStatus::Gereed && $phase->client_approved_at === null && $phase->completed_at !== null) as $phase) {
            $acties->push([
                'type' => 'akkoord',
                'title' => 'Gezien en akkoord: '.$phase->name,
                'subtitle' => 'Bevestig dat u deze afgeronde fase heeft gezien.',
                'phase' => $phase,
            ]);
        }

        if (config('renovion.modules.quotes')) {
            foreach ($project->customer->quotes()->open()->whereNotNull('sent_at')->get() as $quote) {
                $acties->push([
                    'type' => 'offerte',
                    'title' => 'Offerte '.$quote->number.' staat voor u klaar',
                    'subtitle' => 'Bekijk en onderteken de offerte digitaal.',
                    'url' => $quote->publicUrl(),
                ]);
            }
        }

        if ($project->deliveryReport !== null && ! $project->deliveryReport->isSignedByClient()) {
            $acties->push([
                'type' => 'opleverrapport',
                'title' => 'Opleverrapport staat voor u klaar',
                'subtitle' => 'Bekijk het rapport met foto\'s en restpunten en onderteken digitaal.',
                'url' => route('portal.report', $project->deliveryReport),
            ]);
        }

        return $acties;
    }
}
