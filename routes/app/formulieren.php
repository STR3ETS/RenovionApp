<?php

/*
 * Formulieren in de app (mobile/app/screens/*formulier.*): aanmaken en bewerken van aanvragen, projecten,
 * offerteposten, calculaties, begrotingen, prijsaanvragen en teamleden. Geladen vanuit routes/api.php binnen
 * de groep voor intern personeel (prefix app/, namen api.app.*). Alles onder formulieren/ zodat het nooit
 * botst met de lijst- en detailschermen. GET levert keuzelijsten en huidige waarden (Api\App\FormController);
 * opslaan gaat via dezelfde controllers als de website, die bij expectsJson() {id, path} teruggeven.
 */

use App\Http\Controllers\Api\App\FormController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CalculationAssistController;
use App\Http\Controllers\CalculationController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PriceItemSearchController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteLineProposalController;
use App\Http\Controllers\RfqController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('formulieren')->name('forms.')->group(function () {
    Route::middleware('can:manage-crm')->group(function () {
        Route::get('aanvragen/nieuw', [FormController::class, 'leadCreate'])->name('leads.create');
        Route::post('aanvragen', [LeadController::class, 'store'])->middleware('throttle:30,1')->name('leads.store');
        Route::get('aanvragen/{lead}', [FormController::class, 'leadEdit'])->name('leads.edit');
        Route::patch('aanvragen/{lead}', [LeadController::class, 'update'])->middleware('throttle:30,1')->name('leads.update');

        Route::get('projecten/nieuw', [FormController::class, 'projectCreate'])->name('projects.create');
        Route::post('projecten', [ProjectController::class, 'store'])->middleware('throttle:30,1')->name('projects.store');
        Route::get('projecten/{project}', [FormController::class, 'projectEdit'])->name('projects.edit');
        // Met een omslagfoto gaat het als FormData: POST met _method=PATCH (PHP leest multipart alleen bij POST).
        Route::patch('projecten/{project}', [ProjectController::class, 'update'])->middleware('throttle:30,1')->name('projects.update');

        if (config('renovion.modules.quotes')) {
            Route::get('offertes/nieuw', [FormController::class, 'quoteCreate'])->name('quotes.create');
            Route::post('offertes', [QuoteController::class, 'store'])->middleware('throttle:30,1')->name('quotes.store');
            Route::get('offertes/{quote}', [FormController::class, 'quoteEdit'])->name('quotes.edit');
            Route::patch('offertes/{quote}', [QuoteController::class, 'update'])->middleware('throttle:30,1')->name('quotes.update');
            Route::post('offertes/{quote}/posten-voorstel', QuoteLineProposalController::class)->middleware('throttle:15,1')->name('quotes.propose');
        }

        Route::get('prijsitems', PriceItemSearchController::class)->middleware('throttle:60,1')->name('price-items');

        Route::get('calculaties/nieuw', [FormController::class, 'calculationCreate'])->name('calculations.create');
        Route::post('calculaties', [CalculationController::class, 'store'])->middleware('throttle:30,1')->name('calculations.store');
        Route::post('calculaties/ai-voorstel', CalculationAssistController::class)->middleware('throttle:30,1')->name('calculations.propose');

        Route::get('begrotingen/nieuw', [FormController::class, 'budgetCreate'])->name('budgets.create');
        Route::post('begrotingen', [BudgetController::class, 'store'])->middleware('throttle:30,1')->name('budgets.store');

        Route::get('prijsaanvragen/nieuw', [FormController::class, 'rfqCreate'])->name('rfqs.create');
        Route::post('prijsaanvragen', [RfqController::class, 'store'])->middleware('throttle:30,1')->name('rfqs.store');
    });

    // Teambeheer: alleen admins, zoals op de website.
    Route::middleware('can:manage-team')->group(function () {
        Route::get('team/nieuw', [FormController::class, 'teamCreate'])->name('team.create');
        Route::post('team', [TeamController::class, 'store'])->middleware('throttle:30,1')->name('team.store');
        Route::get('team/{user}', [FormController::class, 'teamEdit'])->name('team.edit');
        Route::patch('team/{user}', [TeamController::class, 'update'])->middleware('throttle:30,1')->name('team.update');
    });
});
