<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Models\AuditLog;
use App\Models\JobExecution;
use App\Support\App\FieldWork;
use App\Support\JobChecklistTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * De statusknop op het klusscherm: onderweg, starten, pauzeren en hervatten.
 * Starten kan alleen na de veiligheidscheck, dus daar vragen we de vijf vragen
 * van de LMRA mee op. De app (api/app/klussen) krijgt dezelfde meldingen als JSON.
 */
class JobStatusController extends Controller
{
    public function update(Request $request, JobExecution $job): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->id === $job->user_id, 403);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:onderweg,bezig,pauze'],
            // Bij starten: de vijf LMRA-vragen moeten alle vijf ja zijn.
            'lmra' => ['array'],
        ]);

        $target = JobStatus::from($data['status']);

        return match ($target) {
            JobStatus::Onderweg => $this->onTheWay($request, $job),
            JobStatus::Bezig => $this->start($request, $job, $data['lmra'] ?? []),
            JobStatus::Pauze => $this->pause($request, $job),
            default => back(),
        };
    }

    private function onTheWay(Request $request, JobExecution $job): RedirectResponse|JsonResponse
    {
        $job->update(['status' => JobStatus::Onderweg]);
        AuditLog::record($job, 'onderweg');

        return FieldWork::done($request, 'Onderweg gemeld. Fijne rit.');
    }

    /**
     * Starten of hervatten. Bij de eerste start moet de LMRA rond zijn; na een
     * pauze tellen we de pauzetijd op zodat de urenschatting blijft kloppen.
     */
    private function start(Request $request, JobExecution $job, array $lmra): RedirectResponse|JsonResponse
    {
        if ($job->status === JobStatus::Pauze) {
            $minutes = $job->paused_at === null ? 0 : $job->paused_at->diffInMinutes(now());

            $job->update([
                'status' => JobStatus::Bezig,
                'paused_at' => null,
                'paused_minutes' => $job->paused_minutes + $minutes,
            ]);

            AuditLog::record($job, 'werk hervat');

            return FieldWork::done($request, 'Werk hervat.');
        }

        $answered = collect($lmra)->filter(fn ($value) => (bool) $value)->count();

        if ($answered < count(JobChecklistTemplates::lmra())) {
            return FieldWork::refuse($request, 'Alle vijf vragen van de veiligheidscheck moeten ja zijn voordat je begint.');
        }

        $job->update([
            'status' => JobStatus::Bezig,
            'lmra_confirmed_at' => now(),
            'started_at' => $job->started_at ?? now(),
        ]);

        AuditLog::record($job, 'gestart na veiligheidscheck', [], [
            'lmra' => 'vijf van vijf ja',
        ]);

        return FieldWork::done($request, 'Veiligheidscheck rond, je bent gestart.');
    }

    private function pause(Request $request, JobExecution $job): RedirectResponse|JsonResponse
    {
        $job->update(['status' => JobStatus::Pauze, 'paused_at' => now()]);
        AuditLog::record($job, 'pauze');

        return FieldWork::done($request, 'Pauze ingegaan.');
    }
}
