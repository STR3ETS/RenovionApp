<?php

use App\Http\Controllers\AttentionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\NovaController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectStatusController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteStatusController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () {
    // Voor iedereen (vakmensen zien alleen hun eigen projecten, taken en planning).
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('projecten', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projecten/aanmaken', [ProjectController::class, 'create'])->middleware('can:manage-crm')->name('projects.create');
    Route::get('projecten/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('taken', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('taken', [TaskController::class, 'store'])->name('tasks.store');
    Route::match(['put', 'patch'], 'taken/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('taken/{task}/status', [TaskStatusController::class, 'update'])->name('tasks.status');

    Route::get('planning', [PlanningController::class, 'index'])->name('planning.index');

    Route::post('documenten', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documenten/{document}', [DocumentController::class, 'show'])->name('documents.show');

    // CRM en beheer: niet voor vakmensen (briefing §25).
    Route::middleware('can:manage-crm')->group(function () {
        Route::resource('aanvragen', LeadController::class)
            ->parameters(['aanvragen' => 'lead'])
            ->names('leads')
            ->except('destroy');
        Route::patch('aanvragen/{lead}/status', [LeadStatusController::class, 'update'])->name('leads.status');

        Route::get('klanten', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('klanten/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::post('klanten/{customer}/notities', [CustomerController::class, 'storeNote'])->name('customers.notes.store');

        if (config('renovion.modules.quotes')) {
            Route::resource('offertes', QuoteController::class)
                ->parameters(['offertes' => 'quote'])
                ->names('quotes');
            Route::patch('offertes/{quote}/status', [QuoteStatusController::class, 'update'])->name('quotes.status');
        }

        Route::post('projecten', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('projecten/{project}/bewerken', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::match(['put', 'patch'], 'projecten/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::patch('projecten/{project}/status', [ProjectStatusController::class, 'update'])->name('projects.status');

        Route::post('planning', [PlanningController::class, 'store'])->name('planning.store');
        Route::delete('planning/{scheduleEntry}', [PlanningController::class, 'destroy'])->name('planning.destroy');

        Route::post('nova', [NovaController::class, 'propose'])->middleware('throttle:30,1')->name('nova.propose');
        Route::post('nova/uitvoeren', [NovaController::class, 'execute'])->name('nova.execute');
        Route::get('nova/briefing', [NovaController::class, 'briefing'])->name('nova.briefing');

        Route::get('aandacht', [AttentionController::class, 'index'])->name('attention.index');

        if (config('renovion.modules.automations')) {
            Route::get('automations', [AutomationController::class, 'index'])->name('automations.index');
        }
    });

    // Teambeheer: alleen admins.
    Route::middleware('can:manage-team')->group(function () {
        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::get('team/aanmaken', [TeamController::class, 'create'])->name('team.create');
        Route::post('team', [TeamController::class, 'store'])->name('team.store');
        Route::get('team/{user}/bewerken', [TeamController::class, 'edit'])->name('team.edit');
        Route::match(['put', 'patch'], 'team/{user}', [TeamController::class, 'update'])->name('team.update');
    });
});
