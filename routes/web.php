<?php

use App\Http\Controllers\Auth\ChoosePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Os\AiController;
use App\Http\Controllers\Os\FileController;
use App\Http\Controllers\Os\ShellController;
use App\Http\Controllers\Os\StateController;
use Illuminate\Support\Facades\Route;

/*
| Renovion OS draait als één pagina (de schermen uit het prototype). De server
| levert inloggen, de gedeelde staat en de bestanden.
*/

Route::middleware('guest')->group(function () {
    Route::get('/inloggen', [LoginController::class, 'create'])->name('login');
    Route::post('/inloggen', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

// Oude adressen uit de vorige app
Route::redirect('/login', '/inloggen');
Route::redirect('/dashboard', '/vandaag');

Route::get('/manifest.webmanifest', [ShellController::class, 'manifest'])->name('manifest');

Route::middleware(['auth', 'password.chosen'])->group(function () {
    Route::post('/uitloggen', [LoginController::class, 'destroy'])->name('logout');

    // Na een tijdelijk wachtwoord eerst zelf een eigen wachtwoord kiezen
    Route::get('/wachtwoord-kiezen', [ChoosePasswordController::class, 'create'])->name('password.choose');
    Route::post('/wachtwoord-kiezen', [ChoosePasswordController::class, 'store'])->middleware('throttle:login')->name('password.choose.store');

    Route::get('/', [ShellController::class, 'show'])->name('home');

    Route::prefix('os')->name('os.')->group(function () {
        Route::get('/staat', [StateController::class, 'show'])->name('state');
        Route::get('/wijzigingen', [StateController::class, 'pull'])->name('pull');
        Route::post('/beurt', [StateController::class, 'lease'])->name('lease');
        Route::post('/opslaan', [StateController::class, 'sync'])->middleware('throttle:os-sync')->name('sync');
        Route::post('/bestanden', [FileController::class, 'store'])->middleware('throttle:os-files')->name('files.store');
        Route::get('/bestanden/{file}', [FileController::class, 'show'])->name('files.show');
        Route::post('/accounts/{person}/wachtwoord', [ShellController::class, 'password'])->name('password');

        // Nova en de agents
        Route::post('/nova', [AiController::class, 'ask'])->middleware('throttle:os-ai')->name('nova');
        Route::post('/nova/{proposal}/bevestigen', [AiController::class, 'confirm'])->name('nova.confirm');
        Route::post('/agents/goedkeuringen/{approval}', [AiController::class, 'decide'])->name('agents.decide');
        Route::post('/agents/ronde', [AiController::class, 'round'])->middleware('throttle:os-ai')->name('agents.round');
        Route::post('/werkbon-lezen', [AiController::class, 'readWorkOrder'])->middleware('throttle:os-ai')->name('workorder.read');
        Route::post('/offertetekst', [AiController::class, 'writeOffer'])->middleware('throttle:os-ai')->name('offer.write');
    });

    // Elk scherm heeft een eigen adres (/projecten, /project-pd-2026-0001); de app kiest zelf het scherm.
    Route::get('/{scherm}', [ShellController::class, 'show'])
        ->where('scherm', '[a-z][a-z0-9-]*')
        ->name('screen');
});
