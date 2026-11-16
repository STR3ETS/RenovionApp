<?php

/*
 * Opvolging in de app (mobile/app/screens/opvolging.*). Geladen vanuit routes/api.php binnen de groep voor
 * intern personeel: prefix app/, namen api.app.*. De aanvraag zelf opent het aanvraagscherm van de app.
 */

use App\Http\Controllers\Api\App\FollowUpController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('opvolging', [FollowUpController::class, 'index'])->name('follow-ups.index');
});
