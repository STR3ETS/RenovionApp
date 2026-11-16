<?php

/*
 * "Aan de slag" in de app (mobile/app/screens/aandeslag.*). Geladen vanuit routes/api.php binnen de groep
 * voor intern personeel (prefix app/, namen api.app.*): net als op de website voor het hele team.
 * Verbergen en weer tonen via de OnboardingController van de website, die JSON geeft aan de app.
 */

use App\Http\Controllers\Api\App\OnboardingController as AppOnboardingController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::get('aan-de-slag', [AppOnboardingController::class, 'show'])->name('onboarding');
Route::post('aan-de-slag/verbergen', [OnboardingController::class, 'dismiss'])->middleware('throttle:30,1')->name('onboarding.dismiss');
Route::post('aan-de-slag/tonen', [OnboardingController::class, 'restore'])->middleware('throttle:30,1')->name('onboarding.restore');
