<?php

/*
 * Foto's in de app (mobile/app/screens/fotos.*). Geladen vanuit routes/api.php binnen de groep voor intern
 * personeel: prefix app/, namen api.app.*. Zelfde toegang als de website (web.php): bekijken en uploaden op
 * eigen projecten (toegangscheck in de controllers), omschrijving en klantzichtbaarheid alleen kantoor
 * (can:manage-crm). Verwijderen gaat via api.app.photos.destroy (routes/api.php), de foto zelf via api.app.photo.
 */

use App\Http\Controllers\Api\App\PhotoController as AppPhotoController;
use App\Http\Controllers\PhotoController;
use Illuminate\Support\Facades\Route;

Route::get('projecten/{project}/fotos', [AppPhotoController::class, 'project'])->whereNumber('project')->name('projects.photos');
Route::post('projecten/{project}/fotos', [PhotoController::class, 'store'])->whereNumber('project')->middleware('throttle:30,1')->name('projects.photos.store');
Route::get('fotos/{photo}/gegevens', [AppPhotoController::class, 'show'])->whereNumber('photo')->name('photos.show');
Route::patch('fotos/{photo}', [PhotoController::class, 'update'])->whereNumber('photo')->middleware('can:manage-crm')->name('photos.update');
