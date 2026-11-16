<?php

/*
 * Eigen mensen en het team in de app (mobile/app/screens/mensen.*). Geladen vanuit routes/api.php binnen de
 * groep voor intern personeel: prefix app/, namen api.app.*. Zelfde rechten als de website: mensen voor kantoor
 * (manage-crm, gegevens en certificaten controleert de WorkforceController zelf op manage-team), het team
 * alleen voor manage-team. Teamleden aanmaken en bewerken zijn eigen formulieren (team.create, team.edit).
 */

use App\Http\Controllers\Api\App\TeamOverviewController;
use App\Http\Controllers\Api\App\WorkforceController as AppWorkforceController;
use App\Http\Controllers\WorkforceController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('mensen', [AppWorkforceController::class, 'index'])->name('workforce.index');
    Route::patch('mensen/{user}', [WorkforceController::class, 'update'])->middleware('throttle:30,1')->name('workforce.update');
    Route::post('mensen/{user}/certificaten', [WorkforceController::class, 'storeCertificate'])->middleware('throttle:30,1')->name('certificates.store');
    Route::delete('certificaten/{certificate}', [WorkforceController::class, 'destroyCertificate'])->middleware('throttle:30,1')->name('certificates.destroy');
    Route::post('mensen/{user}/afwezigheid', [WorkforceController::class, 'storeAbsence'])->middleware('throttle:30,1')->name('absences.store');
    Route::delete('afwezigheid/{absence}', [WorkforceController::class, 'destroyAbsence'])->middleware('throttle:30,1')->name('absences.destroy');
});

Route::get('team', TeamOverviewController::class)->middleware('can:manage-team')->name('team.index');
