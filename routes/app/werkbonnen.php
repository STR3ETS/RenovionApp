<?php

/*
 * Werkbonnen in de app (mobile/app/screens/werkbonnen.*). Geladen vanuit routes/api.php binnen de groep voor
 * intern personeel: prefix app/, namen api.app.*. Zoals de website: uitvoerders zien hun eigen bonnen, de
 * projectleiding alles; goedkeuren en terugsturen alleen de projectleiding.
 */

use App\Http\Controllers\Api\App\WorkOrderController as AppWorkOrderController;
use App\Http\Controllers\WorkOrderApprovalController;
use Illuminate\Support\Facades\Route;

Route::get('werkbonnen', [AppWorkOrderController::class, 'index'])->name('work-orders.index');
Route::get('werkbonnen/{workOrder}', [AppWorkOrderController::class, 'show'])->name('work-orders.show');

Route::middleware('can:manage-crm')->group(function () {
    Route::post('werkbonnen/{workOrder}/goedkeuren', [WorkOrderApprovalController::class, 'approve'])->name('work-orders.approve');
    Route::post('werkbonnen/{workOrder}/afkeuren', [WorkOrderApprovalController::class, 'reject'])->name('work-orders.reject');
});
