<?php

/*
 * Inkoop in de app (mobile/app/screens/inkoop.*). Geladen vanuit routes/api.php binnen de groep voor intern
 * personeel: prefix app/, namen api.app.*. Zelfde rechten als de website (manage-crm); wijzigingen via
 * PurchaseOrderController en PurchaseInvoiceController van de website.
 */

use App\Http\Controllers\Api\App\PurchaseOrderController as AppPurchaseOrderController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('inkoop', [AppPurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::post('inkoop', [PurchaseOrderController::class, 'store'])->middleware('throttle:30,1')->name('purchase-orders.store');
    Route::get('inkoop/{purchaseOrder}', [AppPurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::patch('inkoop/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->middleware('throttle:30,1')->name('purchase-orders.update');
    Route::post('inkoop/{purchaseOrder}/regels', [PurchaseOrderController::class, 'storeLine'])->middleware('throttle:60,1')->name('purchase-order-lines.store');
    Route::delete('inkoopregels/{line}', [PurchaseOrderController::class, 'destroyLine'])->middleware('throttle:60,1')->name('purchase-order-lines.destroy');
    Route::post('inkoopfacturen', [PurchaseInvoiceController::class, 'store'])->middleware('throttle:30,1')->name('purchase-invoices.store');
    Route::patch('inkoopfacturen/{purchaseInvoice}', [PurchaseInvoiceController::class, 'update'])->middleware('throttle:30,1')->name('purchase-invoices.update');
});
