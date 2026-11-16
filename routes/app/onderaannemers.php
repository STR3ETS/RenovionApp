<?php

/*
 * Onderaannemers in de app (mobile/app/screens/onderaannemers.*). Geladen vanuit routes/api.php binnen de groep
 * voor intern personeel: prefix app/, namen api.app.*. Zelfde rechten als de website (manage-crm); wijzigingen
 * via de SubcontractorController van de website.
 */

use App\Http\Controllers\Api\App\SubcontractorController as AppSubcontractorController;
use App\Http\Controllers\SubcontractorController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('onderaannemers', [AppSubcontractorController::class, 'index'])->name('subcontractors.index');
    Route::post('onderaannemers', [SubcontractorController::class, 'store'])->middleware('throttle:30,1')->name('subcontractors.store');
    Route::get('onderaannemers/{subcontractor}', [AppSubcontractorController::class, 'show'])->name('subcontractors.show');
    Route::patch('onderaannemers/{subcontractor}', [SubcontractorController::class, 'update'])->middleware('throttle:30,1')->name('subcontractors.update');
    Route::post('onderaannemers/{subcontractor}/dossier', [SubcontractorController::class, 'storeDocument'])->middleware('throttle:30,1')->name('subcontractor-documents.store');
    Route::post('onderaannemers/{subcontractor}/portaal', [SubcontractorController::class, 'storePortalAccount'])->middleware('throttle:10,1')->name('subcontractors.portal');
});
