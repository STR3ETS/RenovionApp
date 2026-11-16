<?php

/*
 * Opleverrapport in de app (mobile/app/screens/opleverrapport.*). Geladen vanuit routes/api.php binnen de groep
 * voor intern personeel: prefix app/, namen api.app.*. Bekijken mag het hele team op eigen projecten (controle in
 * de controller); hergenereren en ondertekenen namens Renovion alleen de projectleiding, zoals op de website.
 */

use App\Http\Controllers\Api\App\DeliveryReportController as AppDeliveryReportController;
use App\Http\Controllers\DeliveryReportController;
use Illuminate\Support\Facades\Route;

Route::get('opleverrapporten/{deliveryReport}', [AppDeliveryReportController::class, 'show'])->name('delivery-reports.show');

Route::middleware('can:manage-crm')->group(function () {
    Route::post('projecten/{project}/opleverrapport', [DeliveryReportController::class, 'store'])->name('delivery-reports.store');
    Route::post('opleverrapporten/{deliveryReport}/ondertekenen', [DeliveryReportController::class, 'sign'])->name('delivery-reports.sign');
});
