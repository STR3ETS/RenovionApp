<?php

/*
 * Nazorg en service in de app (mobile/app/screens/nazorg.*). Geladen vanuit routes/api.php binnen de groep voor
 * intern personeel: prefix app/, namen api.app.*. Alleen de projectleiding, zoals op de website (can:manage-crm).
 */

use App\Http\Controllers\Api\App\ServiceTicketController as AppServiceTicketController;
use App\Http\Controllers\ServiceTicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('nazorg', [AppServiceTicketController::class, 'index'])->name('service.index');
    Route::post('nazorg', [ServiceTicketController::class, 'store'])->middleware('throttle:30,1')->name('service.store');
    Route::patch('nazorg/{serviceTicket}', [ServiceTicketController::class, 'update'])->name('service.update');
});
