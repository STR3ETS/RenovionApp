<?php

/*
 * Offertes (lijst) en het offertedashboard in de app (mobile/app/screens/offertes.*, offertedashboard.*). Geladen
 * vanuit routes/api.php binnen de groep voor intern personeel: prefix app/, namen api.app.*. De offerte zelf staat
 * in routes/app/offertes.php.
 */

use App\Http\Controllers\Api\App\QuoteDashboardController;
use App\Http\Controllers\Api\App\QuoteListController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    if (config('renovion.modules.quotes')) {
        Route::get('offertes', [QuoteListController::class, 'index'])->name('quotes.index');
    }

    Route::get('offertedashboard', QuoteDashboardController::class)->name('quote-dashboard');
});
