<?php

/*
 * Nova en de agents in de app (mobile/app/screens/agents.*): goedkeuringen, uitvoeringen en skills.
 * Geladen vanuit routes/api.php binnen de groep voor intern personeel: prefix app/, namen api.app.*.
 * Rechten als op de website: kantoor (manage-crm), skills alleen de directie (manage-team).
 * Besluiten en skills wijzigen via de AgentController van de website, die JSON geeft aan de app.
 */

use App\Http\Controllers\AgentController;
use App\Http\Controllers\Api\App\AgentController as AppAgentController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('goedkeuringen', [AppAgentController::class, 'approvals'])->name('approvals');
    Route::post('goedkeuringen/{approval}', [AgentController::class, 'decide'])->middleware('throttle:30,1')->name('approvals.decide');
    Route::get('agents', [AppAgentController::class, 'runs'])->name('agents');

    Route::middleware('can:manage-team')->group(function () {
        Route::get('skills', [AppAgentController::class, 'skills'])->name('skills');
        Route::patch('skills/{skill}', [AgentController::class, 'updateSkill'])->middleware('throttle:30,1')->name('skills.update');
    });
});
