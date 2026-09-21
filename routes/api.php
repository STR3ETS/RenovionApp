<?php

use App\Http\Controllers\Api\LeadIntakeController;
use App\Http\Middleware\VerifyLeadIntakeToken;
use Illuminate\Support\Facades\Route;

Route::post('/aanvragen', LeadIntakeController::class)
    ->middleware(VerifyLeadIntakeToken::class)
    ->name('api.leads.intake');
