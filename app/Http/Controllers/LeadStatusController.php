<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadStatusController extends Controller
{
    public function update(Request $request, Lead $lead): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'position' => ['nullable', 'integer', 'min:0'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $old = $lead->status;
        $new = LeadStatus::from($validated['status']);

        $lead->update([
            'status' => $new,
            'position' => $validated['position'] ?? $lead->position,
            'lost_reason' => $new === LeadStatus::Verloren ? ($validated['lost_reason'] ?? null) : null,
        ]);

        if ($old !== $new) {
            AuditLog::record($lead, 'status_gewijzigd', ['status' => $old->value], ['status' => $new->value]);

            $lead->customer->recordEvent(
                TimelineEventType::Wijziging,
                "Leadstatus gewijzigd: {$old->label()} → {$new->label()}",
                null,
                $lead,
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => $new->value, 'label' => $new->label()]);
        }

        return back()->with('success', 'Status bijgewerkt.');
    }
}
