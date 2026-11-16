<?php

/*
 * Uitvoering van een project in de app (mobile/app/screens/uitvoering.*): de tab "uitvoering" van het
 * projectdossier. Bekijken voor iedereen die bij het project mag; fasen en werkpakketten beheren is kantoor,
 * net als op de website, via dezelfde controllers.
 */

use App\Http\Controllers\Api\App\ProjectExecutionController;
use App\Http\Controllers\ChecklistItemController;
use App\Http\Controllers\ProjectPhaseController;
use App\Http\Controllers\ProjectStatusController;
use App\Http\Controllers\WorkPackageController;
use Illuminate\Support\Facades\Route;

Route::get('projecten/{project}/uitvoering', ProjectExecutionController::class)->name('projects.execution');

Route::middleware('can:manage-crm')->group(function () {
    Route::patch('fasen/{phase}', [ProjectPhaseController::class, 'update'])->middleware('throttle:30,1')->name('phases.update');
    Route::post('fasen/{phase}/vrijgeven', [ProjectPhaseController::class, 'approve'])->middleware('throttle:30,1')->name('phases.approve');
    Route::post('projecten/{project}/werkpakketten', [WorkPackageController::class, 'store'])->middleware('throttle:30,1')->name('work-packages.store');
    // Checklist van een werkpakket aanvullen (werkpakketscherm in de app).
    Route::post('werkpakketten/{workPackage}/items', [ChecklistItemController::class, 'store'])->middleware('throttle:60,1')->name('checklist-items.store');
    // Projectstatus (voorbereiding t/m afgerond) vanuit het dossier in de app.
    Route::patch('projecten/{project}/status', [ProjectStatusController::class, 'update'])->middleware('throttle:30,1')->name('projects.status');
});
