<?php

namespace App\Http\Controllers;

use App\Enums\PhaseStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\ProjectPhase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectPhaseController extends Controller
{
    public function update(Request $request, ProjectPhase $phase): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(PhaseStatus::class)],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date'],
        ]);

        // Gereed loopt via de gate (approve), zodat de werkpakket-check geldt.
        if (($validated['status'] ?? null) === PhaseStatus::Gereed->value) {
            return $this->approve($request, $phase);
        }

        if (array_key_exists('status', $validated)) {
            $validated['completed_at'] = null;
            $validated['approved_at'] = null;
            $validated['approved_by'] = null;
        }

        $old = $phase->status;
        $phase->update($validated);

        if ($phase->wasChanged('status')) {
            AuditLog::record($phase, 'status_gewijzigd', ['status' => $old->value], ['status' => $phase->status->value]);
        }

        $phase->project->syncProgress();

        return back()->with('success', 'Fase "'.$phase->name.'" bijgewerkt.');
    }

    /**
     * Goedkeuringsgate (briefing §7): een fase kan pas gereed als alle
     * werkpakketten in de fase gereed zijn. De vrijgave wordt vastgelegd
     * (wie, wanneer, notitie) en de volgende fase start automatisch.
     */
    public function approve(Request $request, ProjectPhase $phase): RedirectResponse
    {
        $validated = $request->validate([
            'gate_note' => ['nullable', 'string', 'max:255'],
        ]);

        $phase->load('workPackages');

        $open = $phase->workPackages->reject(fn ($package) => $package->isDone());

        if ($open->isNotEmpty()) {
            return back()->with('error', 'Fase kan nog niet worden vrijgegeven: '.$open->count().' '.($open->count() === 1 ? 'werkpakket is' : 'werkpakketten zijn').' nog niet gereed ('.$open->pluck('name')->take(3)->implode(', ').').');
        }

        $phase->update([
            'status' => PhaseStatus::Gereed,
            'completed_at' => now(),
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'gate_note' => $validated['gate_note'] ?? null,
        ]);

        // Volgende fase automatisch starten.
        $next = $phase->project->phases()
            ->where('position', '>', $phase->position)
            ->where('status', PhaseStatus::NietGestart)
            ->orderBy('position')
            ->first();

        $next?->update(['status' => PhaseStatus::Bezig]);

        AuditLog::record($phase, 'gate_vrijgegeven', [], [
            'fase' => $phase->name,
            'notitie' => $validated['gate_note'] ?? '—',
        ]);
        $phase->project->customer->recordEvent(
            TimelineEventType::Projectupdate,
            'Fase afgerond: '.$phase->name.($next ? ' — gestart met '.$next->name : ''),
            $validated['gate_note'] ?? null,
            $phase,
        );

        $phase->project->syncProgress();

        return back()->with('success', 'Fase "'.$phase->name.'" vrijgegeven.'.($next ? ' Fase "'.$next->name.'" is gestart.' : ''));
    }
}
