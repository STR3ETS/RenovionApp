<?php

namespace App\Services;

use App\Models\AgentApproval;
use App\Models\AgentEvent;
use App\Models\AgentRun;
use App\Models\AgentSkillSetting;
use App\Models\Project;
use App\Models\User;
use App\Support\AgentRegistry;
use Illuminate\Support\Facades\DB;

/**
 * De orkestratie van de agents: een uitvoering starten, een goedkeuring vragen
 * en een besluit vastleggen.
 *
 * Hier zitten de grenzen in. Een gepauzeerde skill draait niet. Een skill op het
 * niveau voorbereiden levert een goedkeuringsverzoek op in plaats van een actie.
 * En een bedrag boven de grens gaat altijd langs een mens, ook als het niveau
 * dat niet vraagt.
 */
class AgentOrchestrator
{
    /**
     * Een skill starten. Geeft null terug als het niet mag, met de reden in
     * $refusal, zodat het scherm kan zeggen waarom er niets gebeurde.
     */
    public function start(string $skill, ?Project $project = null, ?User $user = null, ?string $trigger = null, ?string &$refusal = null): ?AgentRun
    {
        $definition = AgentRegistry::skill($skill);

        if ($definition === null) {
            $refusal = "De skill {$skill} bestaat niet.";

            return null;
        }

        $setting = AgentSkillSetting::effective($skill);

        if ($setting['paused']) {
            $refusal = AgentRegistry::skill($skill)['name'].' staat op pauze.';

            return null;
        }

        $run = AgentRun::create([
            'reference' => AgentRun::nextReference(),
            'agent' => $definition['agent'],
            'skill' => $skill,
            'project_id' => $project?->id,
            'status' => AgentRun::STATUS_RUNNING,
            'trigger' => $trigger,
            'started_at' => now(),
            'started_by' => $user?->id,
        ]);

        AgentEvent::emit(
            type: 'run.started',
            actor: 'agent:'.$definition['agent'],
            payload: ['skill' => $skill, 'niveau' => $setting['level'], 'aanleiding' => $trigger],
            run: $run,
        );

        return $run;
    }

    /**
     * Om een besluit vragen. Het onderwerp wordt als vingerafdruk vastgelegd,
     * zodat de goedkeuring vervalt als het voorstel daarna verandert.
     */
    public function requestApproval(AgentRun $run, string $action, string $summary, mixed $subject, ?array $payload = null): AgentApproval
    {
        $definition = AgentRegistry::skill($run->skill);

        $approval = AgentApproval::create([
            'reference' => AgentApproval::nextReference(),
            'agent_run_id' => $run->id,
            'project_id' => $run->project_id,
            'agent' => $run->agent,
            'skill' => $run->skill,
            'action' => $action,
            'summary' => $summary,
            'payload' => $payload,
            'fingerprint' => AgentApproval::fingerprint($subject),
            'status' => AgentApproval::STATUS_OPEN,
            'approver_role' => $definition['approver'] ?? null,
        ]);

        $run->update(['status' => AgentRun::STATUS_WAITING_APPROVAL]);
        $run->addStep('Goedkeuring gevraagd: '.$summary);

        AgentEvent::emit(
            type: 'approval.requested',
            actor: 'agent:'.$run->agent,
            payload: ['actie' => $action, 'samenvatting' => $summary, 'goedkeurder' => $approval->approver_role],
            run: $run,
            subject: $approval,
        );

        return $approval;
    }

    /**
     * Een besluit vastleggen. Als het onderwerp inmiddels veranderd is, vervalt
     * het verzoek: de mens zou iets anders goedkeuren dan er nu ligt.
     *
     * @return array{status: string, message: string}
     */
    public function decide(AgentApproval $approval, bool $approved, User $user, mixed $currentSubject = null, ?string $reason = null): array
    {
        if (! $approval->isOpen()) {
            return ['status' => $approval->status, 'message' => 'Dit verzoek is al besloten.'];
        }

        if ($currentSubject !== null && ! $approval->matches($currentSubject)) {
            $approval->update([
                'status' => AgentApproval::STATUS_STALE,
                'decided_at' => now(),
                'reason' => 'Het voorstel is gewijzigd nadat de goedkeuring werd gevraagd.',
            ]);

            AgentEvent::emit(
                type: 'approval.stale',
                actor: 'user:'.$user->id,
                payload: ['verzoek' => $approval->reference],
                run: $approval->run,
                subject: $approval,
            );

            return [
                'status' => AgentApproval::STATUS_STALE,
                'message' => 'Het voorstel is gewijzigd sinds de goedkeuring werd gevraagd. Vraag opnieuw om een besluit.',
            ];
        }

        return DB::transaction(function () use ($approval, $approved, $user, $reason) {
            $approval->update([
                'status' => $approved ? AgentApproval::STATUS_APPROVED : AgentApproval::STATUS_DECLINED,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'reason' => $reason,
            ]);

            $run = $approval->run;

            $run?->update([
                'status' => $approved ? AgentRun::STATUS_DONE : AgentRun::STATUS_CANCELLED,
                'finished_at' => now(),
            ]);

            $run?->addStep($approved
                ? "Goedgekeurd door {$user->name}"
                : "Afgewezen door {$user->name}".($reason !== null ? ": {$reason}" : ''));

            AgentEvent::emit(
                type: $approved ? 'approval.granted' : 'approval.declined',
                actor: 'user:'.$user->id,
                payload: ['verzoek' => $approval->reference, 'reden' => $reason],
                run: $run,
                subject: $approval,
            );

            return [
                'status' => $approval->status,
                'message' => $approved
                    ? 'Goedgekeurd. '.$approval->action.' is vrijgegeven.'
                    : 'Afgewezen. De agent voert dit niet uit.',
            ];
        });
    }

    /**
     * Openstaande verzoeken over hetzelfde onderwerp laten vervallen. Dit gebeurt
     * zodra iemand dat onderwerp wijzigt: een goedkeuring op oude informatie is
     * geen goedkeuring.
     */
    public function invalidate(int $projectId, string $action, string $reason): int
    {
        $approvals = AgentApproval::open()
            ->where('project_id', $projectId)
            ->where('action', $action)
            ->get();

        foreach ($approvals as $approval) {
            $approval->update([
                'status' => AgentApproval::STATUS_STALE,
                'decided_at' => now(),
                'reason' => $reason,
            ]);

            AgentEvent::emit(
                type: 'approval.stale',
                actor: 'agent:nova',
                payload: ['verzoek' => $approval->reference, 'reden' => $reason],
                run: $approval->run,
                subject: $approval,
            );
        }

        return $approvals->count();
    }

    /**
     * Welke skills er bij een gebeurtenis horen, volgens de standaardprocedures.
     *
     * @return list<string>
     */
    public function skillsFor(string $event): array
    {
        return AgentRegistry::SOPS[$event]['skills'] ?? [];
    }

    public function finish(AgentRun $run, string $note, float $cost = 0): void
    {
        $run->update([
            'status' => AgentRun::STATUS_DONE,
            'finished_at' => now(),
            'cost' => (float) $run->cost + $cost,
            'note' => $note,
        ]);

        $run->addStep($note);

        AgentEvent::emit(
            type: 'run.finished',
            actor: 'agent:'.$run->agent,
            payload: ['toelichting' => $note],
            run: $run,
        );
    }

    public function fail(AgentRun $run, string $reason): void
    {
        $run->update([
            'status' => AgentRun::STATUS_FAILED,
            'finished_at' => now(),
            'note' => $reason,
        ]);

        AgentEvent::emit(
            type: 'run.failed',
            actor: 'agent:'.$run->agent,
            payload: ['reden' => $reason],
            run: $run,
        );
    }
}
