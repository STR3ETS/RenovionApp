<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\JobExecution;
use App\Models\JobStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stappen afvinken op het klusscherm, met de notitie en het foto-bewijs dat
 * erbij hoort. Een stap met verplichte foto gaat niet af zonder foto: dat is
 * precies het bewijsmoment dat je achteraf niet meer kunt maken.
 */
class JobStepController extends Controller
{
    public function toggle(Request $request, JobExecution $job, JobStep $step): RedirectResponse
    {
        abort_unless($request->user()->id === $job->user_id, 403);
        abort_unless($step->job_execution_id === $job->id, 404);

        if ($step->isDone()) {
            $step->update(['completed_at' => null, 'completed_by' => null]);

            return back()->with('success', 'Stap weer open gezet.');
        }

        $step->load('photos');

        if ($step->needsPhoto()) {
            return back()->with('error', 'Bij deze stap hoort een foto. Maak die eerst.');
        }

        $step->update(['completed_at' => now(), 'completed_by' => $request->user()->id]);

        if ($job->steps()->whereNull('completed_at')->doesntExist()) {
            AuditLog::record($job, 'alle stappen afgevinkt');
        }

        return back()->with('success', 'Stap afgevinkt.');
    }

    public function note(Request $request, JobExecution $job, JobStep $step): RedirectResponse
    {
        abort_unless($request->user()->id === $job->user_id, 403);
        abort_unless($step->job_execution_id === $job->id, 404);

        $step->update($request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]));

        return back()->with('success', 'Notitie opgeslagen.');
    }

    /**
     * Foto bij een stap. De foto landt ook in het projectdossier, want
     * foto-bewijs hoort nooit in een losse galerij te staan.
     */
    public function photo(Request $request, JobExecution $job, JobStep $step): RedirectResponse
    {
        abort_unless($request->user()->id === $job->user_id, 403);
        abort_unless($step->job_execution_id === $job->id, 404);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['image', 'max:10240'],
        ]);

        foreach ($validated['photos'] as $file) {
            $step->photos()->create([
                'project_id' => $job->project_id,
                'uploaded_by' => $request->user()->id,
                'path' => $file->store('project-photos'),
                'caption' => $step->title,
                'client_visible' => false,
            ]);
        }

        $count = count($validated['photos']);

        return back()->with('success', $count === 1 ? 'Foto opgeslagen.' : "{$count} foto's opgeslagen.");
    }
}
