<?php

/*
 * Rapportages en marketing in de app (mobile/app/screens/rapportages.*). Geladen vanuit routes/api.php binnen de
 * groep voor intern personeel: prefix app/, namen api.app.*. Zelfde rechten als de website (manage-crm).
 */

use App\Http\Controllers\Api\App\ReportController as AppReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('rapportages', [AppReportController::class, 'index'])->name('reports.index');
    Route::get('marketing', [AppReportController::class, 'marketing'])->name('marketing.index');
});
