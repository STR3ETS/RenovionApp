<?php

namespace App\Http\Controllers;

use App\Actions\CompleteJob;
use App\Models\JobExecution;
use App\Support\App\FieldWork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Klus afronden: uren, materiaal, opmerkingen en eventueel de naam van de klant
 * die voor gezien tekent. Dat levert direct de werkbon op. De app krijgt de
 * melding en het pad van de nieuwe werkbon als JSON.
 */
class JobCompletionController extends Controller
{
    public function __invoke(Request $request, JobExecution $job, CompleteJob $action): RedirectResponse|JsonResponse
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
            return FieldWork::refuse($request, $e->getMessage());
        }

        return FieldWork::done(
            $request,
            "Werkbon {$order->number} ingediend. De projectleider krijgt hem ter controle.",
            ['work_order' => route('work-orders.show', $order, false)],
            redirect()->route('jobs.index'),
        );
    }
}
