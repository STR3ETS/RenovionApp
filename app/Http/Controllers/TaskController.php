<?php

namespace App\Http\Controllers;

use App\Enums\ActionSource;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'eigenaar' => ['nullable', 'exists:users,id'],
        ]);

        $status = $request->filled('status') ? TaskStatus::from($request->query('status')) : null;

        $tasks = Task::with(['customer', 'project', 'lead', 'owner'])
            ->when($request->user()->cannot('manage-crm'), fn ($query) => $query->where('owner_id', $request->user()->id))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($status === null, fn ($query) => $query->open())
            ->when($request->filled('eigenaar'), fn ($query) => $query->where('owner_id', $request->integer('eigenaar')))
            ->orderByRaw('deadline is null, deadline asc')
            ->paginate(50)
            ->withQueryString();

        $canCrm = $request->user()->can('manage-crm');

        return view('tasks.index', [
            'tasks' => $tasks,
            'status' => $status,
            'users' => $canCrm ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'customers' => $canCrm ? Customer::orderBy('name')->get(['id', 'name']) : collect(),
            'projects' => Project::active()
                ->when(! $canCrm, fn ($query) => $query->whereHas(
                    'craftsmen', fn ($craftsmen) => $craftsmen->where('users.id', $request->user()->id)
                ))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->user()->cannot('manage-crm')) {
            $validated['owner_id'] = $request->user()->id;
        }

        if (empty($validated['customer_id'])) {
            $validated['customer_id'] = match (true) {
                ! empty($validated['project_id']) => Project::find($validated['project_id'])?->customer_id,
                ! empty($validated['lead_id']) => Lead::find($validated['lead_id'])?->customer_id,
                default => null,
            };
        }

        Task::create([
            ...$validated,
            'owner_id' => $validated['owner_id'] ?? Auth::id(),
            'source' => ActionSource::Handmatig,
        ]);

        return back()->with('success', 'Taak aangemaakt.');
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        if ($request->user()->cannot('manage-crm') && $task->owner_id !== $request->user()->id) {
            abort(403);
        }

        $task->update($request->validated());

        return back()->with('success', 'Taak bijgewerkt.');
    }
}
