<?php

/*
 * Mijn klussen in de app (mobile/app/screens/klussen.*). Geladen vanuit routes/api.php binnen de groep voor
 * intern personeel: prefix app/, namen api.app.*. Dezelfde toegang als de website (routes/web.php): uitvoeren
 * mag alleen de eigenaar van het blok, meekijken de projectleiding (controles in de controllers).
 */

use App\Http\Controllers\Api\App\JobController as AppJobController;
use App\Http\Controllers\JobCompletionController;
use App\Http\Controllers\JobReportController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\JobStepController;
use Illuminate\Support\Facades\Route;

Route::get('klussen', [AppJobController::class, 'index'])->name('jobs.index');
Route::get('klussen/{scheduleEntry}', [AppJobController::class, 'show'])->name('jobs.show');
Route::patch('klussen/{job}/status', [JobStatusController::class, 'update'])->name('jobs.status');
Route::patch('klussen/{job}/stappen/{step}/toggle', [JobStepController::class, 'toggle'])->name('job-steps.toggle');
Route::patch('klussen/{job}/stappen/{step}/notitie', [JobStepController::class, 'note'])->name('job-steps.note');
Route::post('klussen/{job}/stappen/{step}/fotos', [JobStepController::class, 'photo'])->middleware('throttle:30,1')->name('job-steps.photos');
Route::post('klussen/{job}/meldingen', [JobReportController::class, 'store'])->middleware('throttle:30,1')->name('job-reports.store');
Route::post('klusmeldingen/{report}/afhandelen', [JobReportController::class, 'resolve'])->name('job-reports.resolve');
Route::post('klussen/{job}/afronden', JobCompletionController::class)->name('jobs.complete');
