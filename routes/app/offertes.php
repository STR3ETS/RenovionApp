<?php

/*
 * Offerte in de app (mobile/app/screens/offerte.*). Geladen vanuit routes/api.php binnen de groep voor
 * intern personeel: prefix app/, namen api.app.*. Status en nieuwe versie via dezelfde controllers als de website.
 */

use App\Http\Controllers\Api\App\QuoteController as AppQuoteController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteStatusController;
use Illuminate\Support\Facades\Route;

if (config('renovion.modules.quotes')) {
    Route::middleware('can:manage-crm')->group(function () {
        Route::get('offertes/{quote}', [AppQuoteController::class, 'show'])->name('quotes.show');
        Route::patch('offertes/{quote}/status', [QuoteStatusController::class, 'update'])->name('quotes.status');
        Route::post('offertes/{quote}/nieuwe-versie', [QuoteController::class, 'newVersion'])->middleware('throttle:30,1')->name('quotes.version');
    });
}
