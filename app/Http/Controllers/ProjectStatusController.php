<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectStatusController extends Controller
{
    public function update(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ProjectStatus::class)],
        ]);

        $old = $project->status;
        $new = ProjectStatus::from($validated['status']);

        if ($old === $new) {
            return $request->expectsJson() ? response()->json(['status' => $new->value, 'label' => $new->label()]) : back();
        }

        $project->update([
            'status' => $new,
            'end_date_actual' => $new === ProjectStatus::Afgerond ? today() : $project->end_date_actual,
            'progress' => $new === ProjectStatus::Afgerond ? 100 : $project->progress,
        ]);

        AuditLog::record($project, 'status_gewijzigd', ['status' => $old->value], ['status' => $new->value]);

        $project->customer->recordEvent(
            TimelineEventType::Projectupdate,
            "Projectstatus {$project->name}: {$old->label()} → {$new->label()}",
            null,
            $project,
        );

        // De app (projectdossier) krijgt de nieuwe status als JSON.
        if ($request->expectsJson()) {
            return response()->json(['status' => $new->value, 'label' => $new->label(), 'message' => "Projectstatus gewijzigd naar {$new->label()}."]);
        }

        return back()->with('success', "Projectstatus gewijzigd naar {$new->label()}.");
    }
}
