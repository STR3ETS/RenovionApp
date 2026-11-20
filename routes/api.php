<?php

use App\Http\Controllers\Api\InboundMailWebhookController;
use App\Http\Controllers\Api\LeadIntakeController;
use App\Http\Middleware\VerifyInboundMailToken;
use App\Http\Middleware\VerifyLeadIntakeToken;
use Illuminate\Support\Facades\Route;

Route::post('/aanvragen', LeadIntakeController::class)
    ->middleware([VerifyLeadIntakeToken::class, 'throttle:30,1'])
    ->name('api.leads.intake');

// De maildienst levert hier af wat op aanvraag@ binnenkomt.
Route::post('/inkomende-mail', InboundMailWebhookController::class)
    ->middleware([VerifyInboundMailToken::class, 'throttle:60,1'])
    ->name('api.inbound-mail');
