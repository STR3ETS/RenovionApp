<?php

/*
 * Intakes in de app (mobile/app/screens/intakes.*). Geladen vanuit routes/api.php binnen de groep voor intern
 * personeel: prefix app/, namen api.app.*. Starten, opslaan, opname plannen en afronden via de IntakeController
 * van de website (JSON bij expectsJson()).
 */

use App\Http\Controllers\Api\App\IntakeController as AppIntakeController;
use App\Http\Controllers\IntakeController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('intakes', [AppIntakeController::class, 'index'])->name('intakes.index');
    Route::post('intakes', [IntakeController::class, 'store'])->middleware('throttle:30,1')->name('intakes.store');
    Route::get('intakes/{intake}', [AppIntakeController::class, 'show'])->name('intakes.show');
    Route::patch('intakes/{intake}', [IntakeController::class, 'update'])->middleware('throttle:60,1')->name('intakes.update');
    Route::post('intakes/{intake}/opname', [IntakeController::class, 'planSurvey'])->middleware('throttle:30,1')->name('intakes.survey');
    Route::post('intakes/{intake}/afronden', [IntakeController::class, 'complete'])->middleware('throttle:30,1')->name('intakes.complete');
});
