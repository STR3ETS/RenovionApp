<?php

/*
 * Het klantportaal zoals de klant het ziet, in de app (mobile/app/screens/klantportaal.*). Geladen vanuit
 * routes/api.php binnen de groep voor intern personeel (prefix app/, namen api.app.*). Kantoor, zoals op de website.
 */

use App\Http\Controllers\Api\App\PortalPreviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('klantportaal', [PortalPreviewController::class, 'index'])->name('portal-preview');
    Route::get('klantportaal/{project}', [PortalPreviewController::class, 'show'])->name('portal-preview.show');
});
