<?php

/*
 * Prijslijst in de app (mobile/app/screens/prijslijst.*). Geladen vanuit routes/api.php binnen de groep voor
 * intern personeel: prefix app/, namen api.app.*. Toevoegen en bewerken via dezelfde controller als de website.
 */

use App\Http\Controllers\Api\App\PriceListController as AppPriceListController;
use App\Http\Controllers\PriceListController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('prijslijst', [AppPriceListController::class, 'index'])->name('price-items.index');
    Route::post('prijslijst', [PriceListController::class, 'store'])->middleware('throttle:30,1')->name('price-items.store');
    Route::patch('prijslijst/{priceItem}', [PriceListController::class, 'update'])->middleware('throttle:60,1')->name('price-items.update');
});
