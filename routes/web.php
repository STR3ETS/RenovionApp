<?php

use App\Http\Controllers\AttentionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\CalculationAssistController;
use App\Http\Controllers\CalculationController;
use App\Http\Controllers\CalculationLineController;
use App\Http\Controllers\CalculationQuoteController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ChecklistItemController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LeadContactController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\NovaController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PriceItemSearchController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectPhaseController;
use App\Http\Controllers\ProjectStatusController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuotePublicController;
use App\Http\Controllers\QuoteStatusController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WorkPackageController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Klantview van de offerte: privélink zonder inlog (briefing §6).
if (config('renovion.modules.quotes')) {
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('offerte/{quote:public_token}', [QuotePublicController::class, 'show'])->name('quotes.public');
        Route::post('offerte/{quote:public_token}/ondertekenen', [QuotePublicController::class, 'sign'])->name('quotes.public.sign');
        Route::post('offerte/{quote:public_token}/aanpassing', [QuotePublicController::class, 'requestChange'])->name('quotes.public.change');
        Route::post('offerte/{quote:public_token}/afwijzen', [QuotePublicController::class, 'reject'])->name('quotes.public.reject');
    });
}

Route::middleware('auth')->group(function () {
    // Voor iedereen; klanten worden vanuit het dashboard doorgestuurd naar het portaal.
    Route::get('/', DashboardController::class)->name('dashboard');

    // Klantportaal (briefing §10): rol Klant, alleen eigen projecten en klantzichtbare content.
    Route::get('portaal', [PortalController::class, 'index'])->name('portal.index');
    Route::get('portaal/projecten/{project}', [PortalController::class, 'show'])->name('portal.show');
    Route::post('portaal/fasen/{phase}/akkoord', [PortalController::class, 'approvePhase'])->name('portal.phases.approve');

    // Beeldstreams: intern én klantportaal (toegangscheck in de controller).
    Route::get('projecten/{project}/omslagfoto', [ProjectController::class, 'coverPhoto'])->name('projects.cover');
    Route::get('fotos/{photo}', [PhotoController::class, 'show'])->name('photos.show');

    // Interne omgeving: niet voor klanten.
    Route::middleware('can:internal')->group(function () {
        // Voor het hele team (uitvoerders zien alleen hun eigen projecten, taken en planning).
        Route::get('projecten', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projecten/aanmaken', [ProjectController::class, 'create'])->middleware('can:manage-crm')->name('projects.create');
        Route::get('projecten/{project}', [ProjectController::class, 'show'])->name('projects.show');

        // Foto-bewijs: uploaden kan ook door uitvoerders op eigen projecten.
        Route::post('projecten/{project}/fotos', [PhotoController::class, 'store'])->name('photos.store');
        Route::delete('fotos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');

        // Werkpakketten: uitvoerders werken hierin op eigen projecten (toegangscheck in controller).
        Route::get('werkpakketten/{workPackage}', [WorkPackageController::class, 'show'])->name('work-packages.show');
        Route::post('werkpakketten/{workPackage}/afronden', [WorkPackageController::class, 'complete'])->name('work-packages.complete');
        Route::post('werkpakketten/{workPackage}/heropenen', [WorkPackageController::class, 'reopen'])->name('work-packages.reopen');
        Route::patch('werkpakketten/{workPackage}/items/{item}/toggle', [ChecklistItemController::class, 'toggle'])->scopeBindings()->name('checklist-items.toggle');

        // Teamchat (briefing §11): teamkanalen voor iedereen intern, projectkanalen volgen projecttoegang.
        Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
        Route::post('chat/kanalen', [ChatController::class, 'store'])->name('chat.channels.store');
        Route::get('chat/bijlagen/{message}', [ChatMessageController::class, 'attachment'])->name('chat.attachment');
        Route::post('chat/berichten/{message}/nova-bevestigen', [ChatMessageController::class, 'confirmNova'])->name('chat.nova.confirm');
        Route::post('chat/berichten/{message}/taak', [ChatMessageController::class, 'toTask'])->name('chat.task');
        Route::get('chat/{channel}', [ChatController::class, 'show'])->name('chat.show');
        Route::get('chat/{channel}/berichten', [ChatMessageController::class, 'index'])->name('chat.messages');
        Route::post('chat/{channel}/berichten', [ChatMessageController::class, 'store'])->name('chat.messages.store');

        Route::get('taken', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('taken', [TaskController::class, 'store'])->name('tasks.store');
        Route::match(['put', 'patch'], 'taken/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::patch('taken/{task}/status', [TaskStatusController::class, 'update'])->name('tasks.status');

        Route::get('planning', [PlanningController::class, 'index'])->name('planning.index');

        Route::post('documenten', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('documenten/{document}', [DocumentController::class, 'show'])->name('documents.show');

        // CRM en beheer: niet voor uitvoerders (briefing §15).
        Route::middleware('can:manage-crm')->group(function () {
            Route::resource('aanvragen', LeadController::class)
                ->parameters(['aanvragen' => 'lead'])
                ->names('leads')
                ->except('destroy');
            Route::patch('aanvragen/{lead}/status', [LeadStatusController::class, 'update'])->name('leads.status');
            Route::post('aanvragen/{lead}/contactmomenten', [LeadContactController::class, 'store'])->name('leads.contacts.store');

            Route::get('klanten', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('klanten/{customer}', [CustomerController::class, 'show'])->name('customers.show');
            Route::post('klanten/{customer}/notities', [CustomerController::class, 'storeNote'])->name('customers.notes.store');
            Route::post('klanten/{customer}/portaal', [CustomerController::class, 'storePortalAccount'])->name('customers.portal');

            if (config('renovion.modules.quotes')) {
                Route::resource('offertes', QuoteController::class)
                    ->parameters(['offertes' => 'quote'])
                    ->names('quotes');
                Route::patch('offertes/{quote}/status', [QuoteStatusController::class, 'update'])->name('quotes.status');
                Route::patch('offertes/{quote}/blokken', [QuoteController::class, 'updateBlocks'])->name('quotes.blocks');
                Route::post('offertes/{quote}/template', [QuoteController::class, 'applyTemplate'])->name('quotes.template');
                Route::post('offertes/{quote}/nieuwe-versie', [QuoteController::class, 'newVersion'])->name('quotes.version');
                Route::post('calculaties/{calculation}/offerte', CalculationQuoteController::class)->name('calculations.quote');
            }

            Route::get('calculaties', [CalculationController::class, 'index'])->name('calculations.index');
            Route::get('calculaties/nieuw', [CalculationController::class, 'create'])->name('calculations.create');
            Route::post('calculaties', [CalculationController::class, 'store'])->name('calculations.store');
            Route::post('calculaties/ai-voorstel', CalculationAssistController::class)->middleware('throttle:30,1')->name('calculations.propose');
            Route::get('calculaties/{calculation}', [CalculationController::class, 'show'])->name('calculations.show');
            Route::match(['put', 'patch'], 'calculaties/{calculation}', [CalculationController::class, 'update'])->name('calculations.update');
            Route::delete('calculaties/{calculation}', [CalculationController::class, 'destroy'])->name('calculations.destroy');
            Route::post('calculaties/{calculation}/regels', [CalculationLineController::class, 'store'])->name('calculations.lines.store');
            Route::patch('calculaties/{calculation}/regels/{line}', [CalculationLineController::class, 'update'])->scopeBindings()->name('calculations.lines.update');
            Route::delete('calculaties/{calculation}/regels/{line}', [CalculationLineController::class, 'destroy'])->scopeBindings()->name('calculations.lines.destroy');
            Route::get('prijsitems', PriceItemSearchController::class)->name('price-items.search');

            Route::post('projecten', [ProjectController::class, 'store'])->name('projects.store');
            Route::get('projecten/{project}/bewerken', [ProjectController::class, 'edit'])->name('projects.edit');
            Route::match(['put', 'patch'], 'projecten/{project}', [ProjectController::class, 'update'])->name('projects.update');
            Route::patch('projecten/{project}/status', [ProjectStatusController::class, 'update'])->name('projects.status');

            Route::post('projecten/{project}/werkpakketten', [WorkPackageController::class, 'store'])->name('work-packages.store');
            Route::match(['put', 'patch'], 'werkpakketten/{workPackage}', [WorkPackageController::class, 'update'])->name('work-packages.update');
            Route::delete('werkpakketten/{workPackage}', [WorkPackageController::class, 'destroy'])->name('work-packages.destroy');
            Route::post('werkpakketten/{workPackage}/items', [ChecklistItemController::class, 'store'])->name('checklist-items.store');
            Route::delete('werkpakketten/{workPackage}/items/{item}', [ChecklistItemController::class, 'destroy'])->scopeBindings()->name('checklist-items.destroy');
            Route::patch('fotos/{photo}', [PhotoController::class, 'update'])->name('photos.update');
            Route::patch('fasen/{phase}', [ProjectPhaseController::class, 'update'])->name('phases.update');
            Route::post('fasen/{phase}/vrijgeven', [ProjectPhaseController::class, 'approve'])->name('phases.approve');

            Route::post('planning', [PlanningController::class, 'store'])->name('planning.store');
            Route::delete('planning/{scheduleEntry}', [PlanningController::class, 'destroy'])->name('planning.destroy');

            Route::post('nova', [NovaController::class, 'propose'])->middleware('throttle:30,1')->name('nova.propose');
            Route::post('nova/uitvoeren', [NovaController::class, 'execute'])->name('nova.execute');
            Route::get('nova/briefing', [NovaController::class, 'briefing'])->name('nova.briefing');

            Route::get('aandacht', [AttentionController::class, 'index'])->name('attention.index');

            Route::get('zoeken', SearchController::class)->name('search');

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
});
