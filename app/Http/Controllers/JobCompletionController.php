<?php

namespace App\Http\Controllers;

use App\Actions\CompleteJob;
use App\Models\JobExecution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Klus afronden: uren, materiaal, opmerkingen en eventueel de naam van de klant
 * die voor gezien tekent. Dat levert direct de werkbon op.
 */
class JobCompletionController extends Controller
{
    public function __invoke(Request $request, JobExecution $job, CompleteJob $action): RedirectResponse
    {
        abort_unless($request->user()->id === $job->user_id, 403);

        $validated = $request->validate([
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'materials' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'signed_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $order = $action->handle($job, $request->user(), $validated);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('jobs.index')
            ->with('success', "Werkbon {$order->number} ingediend. De projectleider krijgt hem ter controle.");
    }
}
