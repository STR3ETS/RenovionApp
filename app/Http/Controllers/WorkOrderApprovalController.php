<?php

namespace App\Http\Controllers;

use App\Actions\ApproveWorkOrder;
use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Support\App\FieldWork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Goedkeuren of terugsturen van een werkbon. Goedkeuren is het moment waarop
 * uren kosten worden en de voortgang opschuift, dus dat mag alleen de
 * projectleiding doen. De app (api/app/werkbonnen) krijgt de melding als JSON.
 */
class WorkOrderApprovalController extends Controller
{
    public function approve(Request $request, WorkOrder $workOrder, ApproveWorkOrder $action): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        if ($workOrder->status !== WorkOrderStatus::Controle) {
            return FieldWork::refuse($request, 'Deze werkbon staat niet ter controle.');
        }

        $result = $action->handle($workOrder, $request->user());

        return FieldWork::done($request, sprintf(
            '%s uur geboekt (%s), voortgang %d naar %d procent.',
            rtrim(rtrim(number_format($result['hours'], 2, ',', '.'), '0'), ','),
            '€ '.number_format($result['cost'], 2, ',', '.'),
            $result['progress_before'],
            $result['progress_after'],
        ));
    }

    public function reject(Request $request, WorkOrder $workOrder, ApproveWorkOrder $action): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $action->reject($workOrder, $request->user(), $validated['reason']);

        return FieldWork::done($request, 'Werkbon teruggestuurd naar de uitvoerder.');
    }
}
