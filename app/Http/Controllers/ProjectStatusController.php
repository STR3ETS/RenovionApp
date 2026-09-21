<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectStatusController extends Controller
{
    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ProjectStatus::class)],
        ]);

        $old = $project->status;
        $new = ProjectStatus::from($validated['status']);

        if ($old === $new) {
            return back();
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

        return back()->with('success', "Projectstatus gewijzigd naar {$new->label()}.");
    }
}
