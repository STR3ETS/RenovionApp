<?php

/*
 * Kwaliteit en oplevering in de app (mobile/app/screens/kwaliteit.*). Geladen vanuit routes/api.php binnen de
 * groep voor intern personeel: prefix app/, namen api.app.*. Zoals de website: bekijken mag het hele team op
 * eigen projecten; afvinken, doorzetten, tekenen en restpunten beheren alleen de projectleiding.
 */

use App\Http\Controllers\Api\App\QualityController as AppQualityController;
use App\Http\Controllers\QualityController;
use Illuminate\Support\Facades\Route;

Route::get('kwaliteit', [AppQualityController::class, 'index'])->name('quality.index');
Route::get('kwaliteit/{project}', [AppQualityController::class, 'show'])->name('quality.show');

Route::middleware('can:manage-crm')->group(function () {
    Route::patch('controlepunten/{check}/toggle', [QualityController::class, 'toggleCheck'])->name('quality-checks.toggle');
    Route::post('kwaliteit/{review}/volgende-stap', [QualityController::class, 'advance'])->name('quality.advance');
    Route::post('kwaliteit/{review}/ondertekenen', [QualityController::class, 'sign'])->name('quality.sign');
    Route::post('kwaliteit/{project}/restpunten', [QualityController::class, 'storeSnag'])->name('snags.store');
    Route::patch('restpunten/{snag}', [QualityController::class, 'updateSnag'])->name('snags.update');
    Route::delete('restpunten/{snag}', [QualityController::class, 'destroySnag'])->name('snags.destroy');
});
