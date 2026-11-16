<?php

/*
 * Gespreksverslagen in de app (mobile/app/screens/verslagen.*). Geladen vanuit routes/api.php binnen de groep
 * voor intern personeel: prefix app/, namen api.app.*. Zelfde toegang als de website (web.php, can:internal):
 * het hele team neemt op, het verslag volgt project of eigen opname (Meeting::isAccessibleBy, in de controller).
 * Opnemen en de acties gaan via de MeetingController van de website, die JSON geeft aan de app.
 */

use App\Http\Controllers\Api\App\MeetingController as AppMeetingController;
use App\Http\Controllers\MeetingController;
use Illuminate\Support\Facades\Route;

Route::get('verslagen', [AppMeetingController::class, 'index'])->name('meetings.index');
Route::get('verslagen/opnemen', [AppMeetingController::class, 'create'])->name('meetings.create');
Route::post('verslagen', [MeetingController::class, 'store'])->middleware('throttle:10,1')->name('meetings.store');
Route::post('verslagen/{meeting}/geluid', [MeetingController::class, 'chunk'])->whereNumber('meeting')->middleware('throttle:120,1')->name('meetings.chunk');
Route::post('verslagen/{meeting}/stoppen', [MeetingController::class, 'stop'])->whereNumber('meeting')->name('meetings.stop');
Route::get('verslagen/{meeting}', [AppMeetingController::class, 'show'])->whereNumber('meeting')->name('meetings.show');
Route::get('verslagen/{meeting}/status', [MeetingController::class, 'status'])->whereNumber('meeting')->middleware('throttle:30,1')->name('meetings.status');
Route::post('verslagen/{meeting}/taken', [MeetingController::class, 'tasks'])->whereNumber('meeting')->name('meetings.tasks');
Route::post('verslagen/{meeting}/delen', [MeetingController::class, 'share'])->whereNumber('meeting')->name('meetings.share');
Route::post('verslagen/{meeting}/opnieuw', [MeetingController::class, 'retry'])->whereNumber('meeting')->name('meetings.retry');
Route::delete('verslagen/{meeting}', [MeetingController::class, 'destroy'])->whereNumber('meeting')->name('meetings.destroy');

// De opname voor <audio>: die kan geen token meesturen, dus zonder inlog maar met een ondertekend adres uit het
// verslag (api.app.meetings.show controleert de toegang voordat hij het adres geeft).
Route::get('verslagen/{meeting}/opname', [AppMeetingController::class, 'audio'])
    ->whereNumber('meeting')
    ->withoutMiddleware(['auth:sanctum', 'can:internal'])
    ->middleware('signed:relative')
    ->name('meetings.audio');
