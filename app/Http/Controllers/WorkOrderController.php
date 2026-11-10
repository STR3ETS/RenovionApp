<?php

namespace App\Http\Controllers;

use App\Enums\WorkOrderStatus;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Werkbonnen: de lijst voor de projectleider met wat ter controle staat, en het
 * detailscherm met uren, materiaal, checklist, foto's en handtekening.
 */
class WorkOrderController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('status')->toString();

        $query = WorkOrder::with(['project.customer', 'user', 'approver'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [WorkOrderStatus::Controle->value])
            ->orderByDesc('date');

        // Uitvoerders zien alleen hun eigen bonnen.
        if ($request->user()->cannot('manage-crm')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($filter !== '' && WorkOrderStatus::tryFrom($filter) !== null) {
            $query->where('status', $filter);
        }

        if ($project = $request->integer('project')) {
            $query->where('project_id', $project);
        }

        $counts = WorkOrder::query()
            ->when($request->user()->cannot('manage-crm'), fn ($q) => $q->where('user_id', $request->user()->id))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('work-orders.index', [
            'orders' => $query->paginate(25)->withQueryString(),
            'filter' => $filter,
            'counts' => $counts,
            'projects' => Project::active()->orderBy('name')->get(['id', 'name']),
            'projectFilter' => $request->integer('project'),
        ]);
    }

    public function show(Request $request, WorkOrder $workOrder): View
    {
        abort_unless($workOrder->isAccessibleBy($request->user()), 403);

        $workOrder->load(['project.customer', 'user', 'approver', 'job.steps.photos', 'job.reports', 'scheduleEntry']);

        return view('work-orders.show', [
            'order' => $workOrder,
            'photos' => $workOrder->job?->steps->flatMap->photos ?? collect(),
            'canApprove' => $request->user()->can('manage-crm')
                && $workOrder->status === WorkOrderStatus::Controle,
        ]);
    }

    /**
     * Werkvoorbereiding maakt een werkbon vooruit: wie, wat, wanneer en hoeveel
     * uur ervoor staat. De uitvoerder vult hem later op de bouwplaats aan.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'work_package_id' => ['nullable', Rule::exists('work_packages', 'id')],
            'title' => ['required', 'string', 'max:160'],
            'date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            'budget_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = WorkOrder::create([
            ...$validated,
            'number' => WorkOrder::nextNumber(),
            'status' => WorkOrderStatus::Gepland,
            'budget_hours' => $validated['budget_hours'] ?? 0,
            'hourly_rate' => isset($validated['user_id'])
                ? User::find($validated['user_id'])?->rate()
                : null,
        ]);

        AuditLog::record($order, 'werkbon aangemaakt', [], ['titel' => $order->title]);

        return redirect()
            ->route('work-orders.show', $order)
            ->with('success', "Werkbon {$order->number} aangemaakt.");
    }

    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'status' => ['sometimes', new Enum(WorkOrderStatus::class)],
            'user_id' => ['nullable', 'exists:users,id'],
            'date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            'hours' => ['sometimes', 'numeric', 'min:0', 'max:999'],
            'budget_hours' => ['sometimes', 'numeric', 'min:0', 'max:999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $old = $workOrder->only(array_keys($validated));
        $workOrder->update($validated);

        AuditLog::record($workOrder, 'werkbon bijgewerkt', $old, $validated);

        return back()->with('success', 'Werkbon bijgewerkt.');
    }
}
