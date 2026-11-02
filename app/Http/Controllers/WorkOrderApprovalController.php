<?php

namespace App\Http\Controllers;

use App\Actions\ApproveWorkOrder;
use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Goedkeuren of terugsturen van een werkbon. Goedkeuren is het moment waarop
 * uren kosten worden en de voortgang opschuift, dus dat mag alleen de
 * projectleiding doen.
 */
class WorkOrderApprovalController extends Controller
{
    public function approve(Request $request, WorkOrder $workOrder, ApproveWorkOrder $action): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        if ($workOrder->status !== WorkOrderStatus::Controle) {
            return back()->with('error', 'Deze werkbon staat niet ter controle.');
        }

        $result = $action->handle($workOrder, $request->user());

        return back()->with('success', sprintf(
            '%s uur geboekt (%s), voortgang %d naar %d procent.',
            rtrim(rtrim(number_format($result['hours'], 2, ',', '.'), '0'), ','),
            'EUR '.number_format($result['cost'], 2, ',', '.'),
            $result['progress_before'],
            $result['progress_after'],
        ));
    }

    public function reject(Request $request, WorkOrder $workOrder, ApproveWorkOrder $action): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $action->reject($workOrder, $request->user(), $validated['reason']);

        return back()->with('success', 'Werkbon teruggestuurd naar de uitvoerder.');
    }
}
