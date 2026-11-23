<?php

use App\Http\Controllers\Api\WebsiteLeadController;
use Illuminate\Support\Facades\Route;

// Aanvragen van de Renovion-website (header X-Intake-Token, LEAD_INTAKE_TOKEN in .env).
Route::post('/aanvragen', WebsiteLeadController::class)->middleware('throttle:30,1')->name('api.leads.intake');
