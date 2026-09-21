<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskStatusController extends Controller
{
    public function update(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        if ($request->user()->cannot('manage-crm') && $task->owner_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ]);

        $new = TaskStatus::from($validated['status']);

        $task->update([
            'status' => $new,
            'completed_at' => $new === TaskStatus::Afgerond ? now() : null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['status' => $new->value, 'label' => $new->label()]);
        }

        return back()->with('success', $new === TaskStatus::Afgerond ? 'Taak afgerond.' : 'Taakstatus bijgewerkt.');
    }
}
