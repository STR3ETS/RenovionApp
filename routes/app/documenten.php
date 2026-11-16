<?php

/*
 * Documenten in de app (mobile/app/screens/documenten.*). Geladen vanuit routes/api.php binnen de groep voor
 * intern personeel: prefix app/, namen api.app.*. Zelfde toegang als de website (web.php): de bibliotheek
 * alleen voor kantoor (can:manage-crm), één document openen kan het hele team (can:internal).
 */

use App\Http\Controllers\Api\App\DocumentController as AppDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('documenten', [AppDocumentController::class, 'index'])->middleware('can:manage-crm')->name('documents.index');
Route::get('documenten/{document}', [AppDocumentController::class, 'show'])->whereNumber('document')->name('documents.show');

// Het bestand zelf (foto, PDF): zonder token, met een ondertekend adres uit api.app.documents.show.
Route::get('documenten/{document}/bestand', [AppDocumentController::class, 'file'])
    ->whereNumber('document')
    ->withoutMiddleware(['auth:sanctum', 'can:internal'])
    ->middleware('signed:relative')
    ->name('documents.file');
