<?php

namespace App\Http\Controllers;

use App\Models\AgentApproval;
use App\Models\AgentEvent;
use App\Models\AgentRun;
use App\Models\AgentSkillSetting;
use App\Models\Project;
use App\Services\AgentOrchestrator;
use App\Support\AgentRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Nova en de agents: uitvoeringen, goedkeuringen, audit en de skills met hun
 * standaardprocedures.
 */
class AgentController extends Controller
{
    /** Uitvoeringen: wat er loopt, wat wacht en wat klaar is. */
    public function runs(Request $request): View
    {
        $filter = $request->string('status', 'actief')->toString();

        $query = AgentRun::with(['project', 'approvals', 'starter'])->latest('id');

        if ($filter === 'actief') {
            $query->active();
        } elseif ($filter !== '' && $filter !== 'alles') {
            $query->where('status', $filter);
        }

        if ($agent = $request->string('agent')->toString()) {
            $query->where('agent', $agent);
        }

        $all = AgentRun::all();

        return view('agents.runs', [
            'runs' => $query->paginate(25)->withQueryString(),
            'filter' => $filter,
            'agentFilter' => $request->string('agent')->toString(),
            'counts' => [
                'actief' => $all->whereIn('status', AgentRun::ACTIVE)->count(),
                'alles' => $all->count(),
                ...$all->countBy('status')->all(),
            ],
            'agents' => AgentRegistry::AGENTS,
            'openApprovals' => AgentApproval::open()->count(),
            // Wat de agents tot nu toe aan modelgebruik hebben gekost.
            'cost' => round((float) $all->sum('cost'), 2),
        ]);
    }

    /** Goedkeuringen: wat op een menselijk besluit wacht. */
    public function approvals(Request $request): View
    {
        $approvals = AgentApproval::with(['project', 'run', 'decider'])
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->latest('id')
            ->get();

        return view('agents.approvals', [
            'open' => $approvals->where('status', AgentApproval::STATUS_OPEN),
            'decided' => $approvals->where('status', '!=', AgentApproval::STATUS_OPEN)->take(25),
            'agents' => AgentRegistry::AGENTS,
        ]);
    }

    public function decide(Request $request, AgentApproval $approval, AgentOrchestrator $orchestrator): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $validated = $request->validate([
            'besluit' => ['required', 'string', 'in:goedkeuren,afwijzen'],
            'reden' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $orchestrator->decide(
            $approval,
            $validated['besluit'] === 'goedkeuren',
            $request->user(),
            reason: $validated['reden'] ?? null,
        );

        return back()->with(
            $result['status'] === AgentApproval::STATUS_STALE ? 'error' : 'success',
            $result['message'],
        );
    }

    /** De audit: wie deed wat, een agent of een mens. */
    public function audit(Request $request): View
    {
        $query = AgentEvent::with(['run', 'project'])->latest('occurred_at');

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($project = $request->integer('project')) {
            $query->where('project_id', $project);
        }

        if ($request->string('wie')->toString() === 'agent') {
            $query->where('actor', 'like', 'agent:%');
        } elseif ($request->string('wie')->toString() === 'mens') {
            $query->where('actor', 'like', 'user:%');
        }

        return view('agents.audit', [
            'events' => $query->paginate(50)->withQueryString(),
            'types' => AgentEvent::select('type')->distinct()->orderBy('type')->pluck('type'),
            'type' => $type,
            'wie' => $request->string('wie')->toString(),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'projectFilter' => $request->integer('project'),
        ]);
    }

    /** Skills en standaardprocedures: wat mag welke agent, en wie keurt het goed. */
    public function skills(Request $request): View
    {
        return view('agents.skills', [
            'agents' => AgentRegistry::AGENTS,
            'skills' => AgentRegistry::SKILLS,
            'levels' => AgentRegistry::LEVELS,
            'sops' => AgentRegistry::SOPS,
            'settings' => AgentSkillSetting::allEffective(),
            'runCounts' => AgentRun::selectRaw('skill, count(*) as total')->groupBy('skill')->pluck('total', 'skill'),
        ]);
    }

    public function updateSkill(Request $request, string $skill): RedirectResponse
    {
        abort_unless($request->user()->can('manage-team'), 403);
        abort_if(AgentRegistry::skill($skill) === null, 404);

        $validated = $request->validate([
            'level' => ['nullable', 'string', 'in:'.implode(',', array_keys(AgentRegistry::LEVELS))],
            'paused' => ['nullable', 'boolean'],
            'limit_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $setting = AgentSkillSetting::updateOrCreate(['skill' => $skill], [
            'level' => $validated['level'] ?? null,
            'paused' => $request->boolean('paused'),
            'limit_amount' => $validated['limit_amount'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        AgentEvent::emit(
            type: 'skill.configured',
            actor: 'user:'.$request->user()->id,
            payload: [
                'skill' => $skill,
                'niveau' => $setting->level,
                'pauze' => $setting->paused,
                'grens' => $setting->limit_amount,
            ],
            subject: $setting,
        );

        return back()->with('success', AgentRegistry::skill($skill)['name'].' bijgewerkt.');
    }
}
