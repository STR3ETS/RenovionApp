<?php

namespace App\Http\Controllers;

use App\Actions\CreateTask;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(StoreTaskRequest $request, CreateTask $createTask): RedirectResponse
    {
        $createTask->handle($request->validated(), $request->user());

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
