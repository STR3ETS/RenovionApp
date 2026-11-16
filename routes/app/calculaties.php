<?php

/*
 * Calculaties in de app (mobile/app/screens/calculaties.*). Geladen vanuit routes/api.php binnen de groep voor
 * intern personeel: prefix app/, namen api.app.*. Wijzigen via de controllers van de website (JSON bij
 * expectsJson()); opslagen en status via Api\App\CalculationController@update, die CalculationController@update
 * aanroept. prijsitems is de zoeker in de prijsbibliotheek (ook voor begrotingen).
 */

use App\Http\Controllers\Api\App\CalculationController as AppCalculationController;
use App\Http\Controllers\CalculationController;
use App\Http\Controllers\CalculationLineController;
use App\Http\Controllers\CalculationQuoteController;
use App\Http\Controllers\PriceItemSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('prijsitems', PriceItemSearchController::class)->middleware('throttle:60,1')->name('price-items.search');

    Route::get('calculaties', [AppCalculationController::class, 'index'])->name('calculations.index');
    Route::get('calculaties/{calculation}', [AppCalculationController::class, 'show'])->whereNumber('calculation')->name('calculations.show');
    Route::patch('calculaties/{calculation}', [AppCalculationController::class, 'update'])->whereNumber('calculation')->middleware('throttle:60,1')->name('calculations.update');
    Route::delete('calculaties/{calculation}', [CalculationController::class, 'destroy'])->whereNumber('calculation')->middleware('throttle:30,1')->name('calculations.destroy');

    Route::post('calculaties/{calculation}/regels', [CalculationLineController::class, 'store'])->middleware('throttle:60,1')->name('calculations.lines.store');
    Route::patch('calculaties/{calculation}/regels/{line}', [CalculationLineController::class, 'update'])->scopeBindings()->middleware('throttle:60,1')->name('calculations.lines.update');
    Route::delete('calculaties/{calculation}/regels/{line}', [CalculationLineController::class, 'destroy'])->scopeBindings()->middleware('throttle:60,1')->name('calculations.lines.destroy');

    if (config('renovion.modules.quotes')) {
        Route::post('calculaties/{calculation}/offerte', CalculationQuoteController::class)->middleware('throttle:30,1')->name('calculations.quote');
    }
});
