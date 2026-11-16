<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\Project;
use App\Models\WorkOrder;
use App\Support\App\FieldWork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Werkbonnen in de app (work-orders/index en work-orders/show), met dezelfde filters en toegang als de
 * website: uitvoerders zien hun eigen bonnen, de projectleiding alles. Goedkeuren en terugsturen gaan via
 * WorkOrderApprovalController (JSON-antwoord). De lijst toont de eerste 100; de rest staat op de website.
 */
class WorkOrderController extends Controller
{
    private const LIMIT = 100;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filter = $request->string('status')->toString();
        $filter = WorkOrderStatus::tryFrom($filter) !== null ? $filter : '';
        $projectId = $request->integer('project');

        $query = WorkOrder::with(['project', 'user'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [WorkOrderStatus::Controle->value])
            ->orderByDesc('date');

        // Uitvoerders zien alleen hun eigen bonnen.
        if ($user->cannot('manage-crm')) {
            $query->where('user_id', $user->id);
        }

        if ($filter !== '') {
            $query->where('status', $filter);
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $orders = $query->limit(self::LIMIT + 1)->get();

        $counts = WorkOrder::query()
            ->when($user->cannot('manage-crm'), fn ($q) => $q->where('user_id', $user->id))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $filters = collect(WorkOrderStatus::filters())
            ->reject(fn (WorkOrderStatus $status) => ($counts[$status->value] ?? 0) === 0 && $filter !== $status->value)
            ->map(fn (WorkOrderStatus $status) => ['value' => $status->value, 'label' => $status->label(), 'count' => (int) ($counts[$status->value] ?? 0)])
            ->prepend(['value' => '', 'label' => 'Alles', 'count' => (int) $counts->sum()])
            ->values();

        return response()->json([
            'crm' => $user->can('manage-crm'),
            'filter' => $filter,
            'filters' => $filters,
            'to_review' => (int) ($counts[WorkOrderStatus::Controle->value] ?? 0),
            'project' => $projectId ? Project::whereKey($projectId)->value('name') : null,
            'project_id' => $projectId ?: null,
            'orders' => $orders->take(self::LIMIT)->map(fn (WorkOrder $order) => $this->row($order))->values(),
            'more' => $orders->count() > self::LIMIT,
            'web' => route('work-orders.index', array_filter(['status' => $filter ?: null, 'project' => $projectId ?: null]), false),
        ]);
    }

    public function show(Request $request, WorkOrder $workOrder): JsonResponse
    {
        abort_unless($workOrder->isAccessibleBy($request->user()), 403);

        $workOrder->load(['project.customer', 'user', 'approver', 'job.steps.photos', 'job.reports', 'scheduleEntry']);
        $variance = $workOrder->hoursVariance();
        $checklist = collect($workOrder->checklist ?? []);
        $photos = $workOrder->job?->steps->flatMap->photos ?? collect();

        return response()->json([
            'id' => $workOrder->id,
            'number' => $workOrder->number,
            'title' => $workOrder->title,
            'subtitle' => $workOrder->project->name.', '.($workOrder->user?->name ?? 'niet toegewezen').', '.$workOrder->date->translatedFormat('j F Y'),
            'status' => ['value' => $workOrder->status->value, 'label' => $workOrder->status->label(), 'classes' => $workOrder->status->appBadgeClasses()],
            'rejection_reason' => $workOrder->rejection_reason,
            'hours' => FieldWork::number($workOrder->hours),
            'budget_hours' => FieldWork::number($workOrder->budget_hours),
            'variance' => $variance === null ? null : [
                'text' => ($variance > 0 ? '+' : '').FieldWork::number($variance, 1),
                'over' => $variance > 0,
            ],
            'labor_cost' => $workOrder->hourly_rate !== null ? $workOrder->laborCost() : null,
            'materials' => collect($workOrder->materials ?? [])->map(fn (array $post) => [
                'description' => $post['description'],
                'quantity' => FieldWork::number($post['quantity']).' '.$post['unit'],
            ])->values(),
            'notes' => $workOrder->notes,
            'checklist' => $checklist->map(fn (array $step) => [
                'title' => $step['title'],
                'done' => (bool) $step['done'],
                'note' => filled($step['note'] ?? null) ? $step['note'] : null,
                'photos' => (int) ($step['photos'] ?? 0),
            ])->values(),
            'checklist_done' => $checklist->where('done', true)->count(),
            'photos' => $photos->map(fn (Photo $photo) => FieldWork::photo($photo, 'Bewijsfoto'))->values(),
            'can_approve' => $request->user()->can('manage-crm') && $workOrder->status === WorkOrderStatus::Controle,
            'approved' => $workOrder->approved_at ? [
                'name' => $workOrder->approver?->name,
                'at' => $workOrder->approved_at->translatedFormat('j F Y H:i'),
            ] : null,
            'signed' => $workOrder->isSigned() ? [
                'name' => $workOrder->signed_name,
                'at' => $workOrder->signed_at->translatedFormat('j F Y H:i'),
            ] : null,
            'project' => ['name' => $workOrder->project->name, 'path' => route('projects.show', $workOrder->project, false)],
            'job' => $workOrder->job && $workOrder->schedule_entry_id ? route('jobs.show', $workOrder->schedule_entry_id, false) : null,
            'submitted_at' => $workOrder->submitted_at?->translatedFormat('j M Y H:i'),
            'path' => route('work-orders.show', $workOrder, false),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(WorkOrder $order): array
    {
        $variance = $order->hoursVariance();

        return [
            'id' => $order->id,
            'path' => route('work-orders.show', $order, false),
            'title' => $order->title,
            'status' => ['value' => $order->status->value, 'label' => $order->status->label(), 'classes' => $order->status->appBadgeClasses()],
            'review' => $order->status === WorkOrderStatus::Controle,
            'sub' => $order->number.', '.$order->project->name.', '.($order->user?->name ?? 'niet toegewezen').', '.$order->date->translatedFormat('j M'),
            'hours' => FieldWork::number($order->hours).' uur',
            'variance' => $variance !== null && abs($variance) >= 0.5 ? [
                'text' => ($variance > 0 ? '+' : '').FieldWork::number($variance, 1).' tegenover begroot',
                'over' => $variance > 0,
            ] : null,
            'on_budget' => ($variance === null || abs($variance) < 0.5) && (float) $order->budget_hours > 0,
        ];
    }
}
