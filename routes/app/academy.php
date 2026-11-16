<?php

/*
 * De Academy in de app (mobile/app/screens/academy.*). Geladen vanuit routes/api.php binnen de groep voor
 * intern personeel (prefix app/, namen api.app.*): net als op de website voor iedereen met een interne rol,
 * ook uitvoerders. Afronden via de AcademyController van de website, die JSON geeft aan de app.
 */

use App\Http\Controllers\AcademyController;
use App\Http\Controllers\Api\App\AcademyController as AppAcademyController;
use Illuminate\Support\Facades\Route;

Route::get('academy', [AppAcademyController::class, 'index'])->name('academy');
Route::get('academy/{tour}', [AppAcademyController::class, 'show'])->name('academy.show');
Route::post('academy/{tour}/afronden', [AcademyController::class, 'complete'])->middleware('throttle:30,1')->name('academy.complete');
