<?php

/*
 * Facturatie in de app (mobile/app/screens/facturen.*). Geladen vanuit routes/api.php binnen de groep voor
 * intern personeel: prefix app/, namen api.app.*. Zelfde rechten als de website (manage-crm); factureren,
 * versturen, betaald melden en verwijderen via de InvoiceController van de website.
 */

use App\Http\Controllers\Api\App\InvoiceController as AppInvoiceController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('facturen', [AppInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('facturen/{invoice}', [AppInvoiceController::class, 'show'])->name('invoices.show');
    Route::patch('facturen/{invoice}', [InvoiceController::class, 'update'])->middleware('throttle:30,1')->name('invoices.update');
    Route::delete('facturen/{invoice}', [InvoiceController::class, 'destroy'])->middleware('throttle:30,1')->name('invoices.destroy');
    Route::post('begrotingstermijnen/{term}/factureren', [InvoiceController::class, 'storeTerm'])->middleware('throttle:30,1')->name('invoices.term');
});
