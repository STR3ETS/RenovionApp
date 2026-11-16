<?php

/*
 * Prijsaanvragen in de app (mobile/app/screens/prijsaanvragen.*). Geladen vanuit routes/api.php binnen de groep
 * voor intern personeel: prefix app/, namen api.app.*. Uitnodigen, bieden en gunnen via dezelfde controller als
 * de website; opslaan via Api\App\RfqController@update, die RfqController@update van de website aanroept.
 */

use App\Http\Controllers\Api\App\RfqController as AppRfqController;
use App\Http\Controllers\RfqController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('prijsaanvragen', [AppRfqController::class, 'index'])->name('rfqs.index');
    Route::get('prijsaanvragen/{rfq}', [AppRfqController::class, 'show'])->name('rfqs.show');
    Route::patch('prijsaanvragen/{rfq}', [AppRfqController::class, 'update'])->middleware('throttle:30,1')->name('rfqs.update');
    Route::post('prijsaanvragen/{rfq}/uitnodigen', [RfqController::class, 'invite'])->middleware('throttle:30,1')->name('rfqs.invite');
    Route::post('prijsaanvragen/{rfq}/biedingen', [RfqController::class, 'storeBid'])->middleware('throttle:30,1')->name('rfqs.bids');
    Route::post('prijsaanvragen/{rfq}/gunnen/{bid}', [RfqController::class, 'award'])->middleware('throttle:30,1')->name('rfqs.award');
});
