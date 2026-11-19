<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\AgentApproval;
use App\Models\AgentRun;
use App\Models\AgentSkillSetting;
use App\Support\AgentRegistry;
use App\Support\App\AppColours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nova en de agents in de app: goedkeuringen (agents/approvals), uitvoeringen (agents/runs) en skills
 * (agents/skills), met dezelfde gegevens als de website. Besluiten en skills wijzigen gaan via de
 * AgentController van de website (JSON aan de app), zodat de regels op één plek staan.
 */
class AgentController extends Controller
{
    public function approvals(Request $request): JsonResponse
    {
        $approvals = AgentApproval::with(['project', 'run', 'decider'])
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->latest('id')
            ->get();

        return response()->json([
            'can_decide' => $request->user()->can('manage-crm'),
            'open' => $approvals->where('status', AgentApproval::STATUS_OPEN)->values()->map(fn (AgentApproval $approval) => [
                'id' => $approval->id,
                'action' => $approval->action,
                'summary' => $approval->summary,
                'agent' => AppColours::agent($approval->agentMeta()),
                'waiting_days' => $approval->waitingDays(),
                'meta' => collect([
                    $approval->reference,
                    AgentRegistry::skill($approval->skill)['name'] ?? $approval->skill,
                    $approval->project?->name,
                    $approval->approver_role ? 'beslist door '.$approval->approver_role : null,
                    'gevraagd '.$approval->created_at->diffForHumans(),
                ])->filter()->implode(', '),
                'payload' => collect($approval->payload ?? [])->map(fn ($value, $key) => [
                    'label' => ucfirst(str_replace('_', ' ', (string) $key)),
                    'value' => is_scalar($value) ? (string) $value : json_encode($value),
                ])->values(),
                'project' => $approval->project ? route('projects.show', $approval->project, false) : null,
            ]),
            'decided' => $approvals->where('status', '!=', AgentApproval::STATUS_OPEN)->take(25)->values()->map(fn (AgentApproval $approval) => [
                'id' => $approval->id,
                'action' => $approval->action,
                'summary' => $approval->summary,
                'sub' => collect([
                    $approval->decider?->name ?? 'het systeem',
                    $approval->decided_at?->translatedFormat('j M Y H:i'),
                    $approval->reason,
                ])->filter()->implode(', '),
                'status' => ['value' => $approval->status, 'label' => $approval->statusLabel(), 'classes' => $approval->appBadgeClasses()],
            ]),
        ]);
    }

    public function runs(Request $request): JsonResponse
    {
        $filter = $request->string('status', 'actief')->toString();
        $agentFilter = $request->string('agent')->toString();

        $query = AgentRun::with(['project', 'approvals', 'starter'])->latest('id');

        if ($filter === 'actief') {
            $query->active();
        } elseif ($filter !== '' && $filter !== 'alles') {
            $query->where('status', $filter);
        }

        if ($agentFilter !== '') {
            $query->where('agent', $agentFilter);
        }

        $all = AgentRun::all();
        $counts = [
            'actief' => $all->whereIn('status', AgentRun::ACTIVE)->count(),
            'alles' => $all->count(),
            ...$all->countBy('status')->all(),
        ];
        $runs = $query->paginate(25);

        // Dezelfde tabbladen als de website: een lege status alleen als hij gekozen is.
        $tabs = [];
        foreach (['actief' => 'Actief', 'alles' => 'Alles', AgentRun::STATUS_WAITING_APPROVAL => 'Wacht op goedkeuring', AgentRun::STATUS_DONE => 'Afgerond', AgentRun::STATUS_FAILED => 'Mislukt'] as $key => $label) {
            if (($counts[$key] ?? 0) === 0 && $filter !== $key && ! in_array($key, ['actief', 'alles'], true)) {
                continue;
            }
            $tabs[] = ['key' => $key, 'label' => $label, 'count' => $counts[$key] ?? 0];
        }

        return response()->json([
            'filter' => $filter,
            'agent' => $agentFilter,
            'tabs' => $tabs,
            'open_approvals' => AgentApproval::open()->count(),
            'cost' => round((float) $all->sum('cost'), 2),
            'agents' => collect(AgentRegistry::AGENTS)->map(fn (array $agent, string $id) => [
                'id' => $id,
                ...AppColours::agent($agent),
                'field' => $agent['field'],
                'task' => $agent['task'],
            ])->values(),
            'runs' => $runs->getCollection()->map(function (AgentRun $run) {
                $skill = AgentRegistry::skill($run->skill);

                return [
                    'id' => $run->id,
                    'skill' => $run->skillName(),
                    'status' => ['value' => $run->status, 'label' => $run->statusLabel(), 'classes' => $run->appBadgeClasses()],
                    'agent' => AppColours::agent($run->agentMeta()),
                    'sub' => collect([
                        $run->reference,
                        $run->agentMeta()['name'],
                        $run->project?->name,
                        $run->trigger ? 'aanleiding '.$run->trigger : null,
                        $run->created_at->diffForHumans(),
                    ])->filter()->implode(', '),
                    'duration' => $run->duration(),
                    'duration_label' => $run->durationLabel(),
                    'cost' => (float) $run->cost > 0 ? (float) $run->cost : null,
                    'steps' => collect($run->steps ?? [])->map(fn (array $step) => ['description' => $step['description'], 'at' => $step['at']])->values(),
                    'about' => $skill ? [
                        'level' => AgentRegistry::LEVELS[$skill['level']]['label'],
                        'approver' => $skill['approver'] ?? 'niemand, dit is protocol',
                        'input' => $skill['input'],
                        'output' => $skill['output'],
                    ] : null,
                    'note' => $run->note,
                    'approvals' => $run->approvals->map(fn (AgentApproval $approval) => [
                        'id' => $approval->id,
                        'summary' => $approval->summary,
                        'status' => ['label' => $approval->statusLabel(), 'classes' => $approval->appBadgeClasses()],
                    ])->values(),
                ];
            }),
            'page' => $runs->currentPage(),
            'last_page' => $runs->lastPage(),
            'total' => $runs->total(),
        ]);
    }

    public function skills(Request $request): JsonResponse
    {
        $settings = AgentSkillSetting::allEffective();
        $runCounts = AgentRun::selectRaw('skill, count(*) as total')->groupBy('skill')->pluck('total', 'skill');

        return response()->json([
            'can_edit' => $request->user()->can('manage-team'),
            'levels' => collect(AgentRegistry::LEVELS)->map(fn (array $level, string $key) => [
                'value' => $key,
                'label' => $level['label'],
                'about' => $level['about'],
                'count' => collect($settings)->where('level', $key)->count(),
            ])->values(),
            'total' => count(AgentRegistry::SKILLS),
            'sops' => collect(AgentRegistry::SOPS)->map(fn (array $sop, string $event) => [
                'event' => $event,
                'label' => $sop['label'],
                'skills' => collect($sop['skills'])->map(function (string $skillId) {
                    $agent = AgentRegistry::agent(AgentRegistry::SKILLS[$skillId]['agent']);

                    return ['label' => $agent['name'].': '.AgentRegistry::SKILLS[$skillId]['name'], 'classes' => AppColours::chip($agent['colour'])];
                })->values(),
            ])->values(),
            'agents' => collect(AgentRegistry::AGENTS)
                ->map(fn (array $agent, string $agentId) => [
                    'id' => $agentId,
                    ...AppColours::agent($agent),
                    'field' => $agent['field'],
                    'task' => $agent['task'],
                    'owner' => $agent['owner'],
                    'skills' => collect(AgentRegistry::SKILLS)
                        ->filter(fn (array $skill) => $skill['agent'] === $agentId)
                        ->map(function (array $skill, string $skillId) use ($settings, $runCounts) {
                            $setting = $settings[$skillId];
                            $runs = (int) ($runCounts[$skillId] ?? 0);

                            return [
                                'id' => $skillId,
                                'name' => $skill['name'],
                                'level' => $setting['level'],
                                'level_label' => AgentRegistry::LEVELS[$setting['level']]['label'],
                                'paused' => $setting['paused'],
                                'overridden' => $setting['overridden'],
                                'limit' => $setting['limit'],
                                'about' => 'Invoer: '.$skill['input'].'. Uitkomst: '.$skill['output'].'.',
                                'decides' => collect([
                                    'Beslist: '.($skill['approver'] ?? 'niemand, dit is een vastgestelde actie'),
                                    $setting['limit'] !== null ? 'boven € '.number_format($setting['limit'], 0, ',', '.').' altijd een mens' : null,
                                    $runs > 0 ? $runs.' '.($runs === 1 ? 'uitvoering' : 'uitvoeringen') : null,
                                ])->filter()->implode(', '),
                            ];
                        })->values(),
                ])
                ->filter(fn (array $agent) => $agent['skills']->isNotEmpty())
                ->values(),
        ]);
    }
}
