<?php

/*
 * Organisatie-instellingen in de app (mobile/app/screens/organisatie.*): bedrijfsgegevens en standaardwaarden.
 * Geladen vanuit routes/api.php binnen de groep voor intern personeel (prefix app/, namen api.app.*).
 * Rechten als op de website: alleen manage-team. Opslaan via SettingController@update (JSON aan de app).
 */

use App\Http\Controllers\Api\App\CompanySettingsController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-team')->group(function () {
    Route::get('organisatie', [CompanySettingsController::class, 'show'])->name('company-settings');
    Route::patch('organisatie', [SettingController::class, 'update'])->middleware('throttle:30,1')->name('company-settings.update');
});
