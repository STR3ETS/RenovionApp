<?php

/*
 * Financieel en nacalculatie in de app (mobile/app/screens/financieel.*). Geladen vanuit routes/api.php binnen
 * de groep voor intern personeel: prefix app/, namen api.app.*. Zelfde rechten als de website (manage-crm).
 */

use App\Http\Controllers\Api\App\FinanceController as AppFinanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('financieel', [AppFinanceController::class, 'index'])->name('finance.index');
    Route::get('nacalculatie', [AppFinanceController::class, 'costReviews'])->name('cost-reviews.index');
});
