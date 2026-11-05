<?php

namespace App\Http\Controllers\Api\App;

use App\Actions\CreateTask;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\AppPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Taken in de iOS/Android-app: dezelfde selectie als de takenlijst van de website. */
class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::enum(TaskStatus::class)]]);

        $user = $request->user();
        $crm = $user->can('manage-crm');
        $status = $request->filled('status') ? TaskStatus::from($request->query('status')) : null;

        $tasks = Task::with(['customer', 'project', 'owner'])
            ->when(! $crm, fn ($query) => $query->where('owner_id', $user->id))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($status === null, fn ($query) => $query->open())
            ->orderByRaw('deadline is null, deadline asc')
            ->limit(100)
            ->get();

        return response()->json([
            'statuses' => [
                ['value' => null, 'label' => 'Open'],
                ...array_map(fn (TaskStatus $option) => ['value' => $option->value, 'label' => $option->label()], TaskStatus::cases()),
            ],
            'tasks' => $tasks->map(fn (Task $task) => [
                ...AppPayload::task($task),
                'owner' => $task->owner?->name,
                'project' => $task->project ? ['name' => $task->project->name, 'path' => route('projects.show', $task->project, false)] : null,
                'customer' => $task->customer?->name,
                'status' => ['value' => $task->status->value, 'label' => $task->status->label(), 'open' => $task->status->isOpen()],
                'priority' => ['label' => $task->priority->label(), 'dot' => $task->priority->dotClasses()],
            ]),
            'form' => [
                'users' => $crm ? User::where('role', '!=', 'klant')->orderBy('name')->get(['id', 'name']) : [],
                'projects' => Project::active()
                    ->when(! $crm, fn ($query) => $query->whereHas('craftsmen', fn ($craftsmen) => $craftsmen->where('users.id', $user->id)))
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ]);
    }

    public function store(StoreTaskRequest $request, CreateTask $createTask): JsonResponse
    {
        $task = $createTask->handle($request->validated(), $request->user());

        return response()->json(['id' => $task->id], 201);
    }
}
