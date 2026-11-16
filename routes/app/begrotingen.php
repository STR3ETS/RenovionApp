<?php

/*
 * Begrotingen in de app (mobile/app/screens/begrotingen.*). Geladen vanuit routes/api.php binnen de groep voor
 * intern personeel: prefix app/, namen api.app.*. Elke wijziging gaat via dezelfde controller als de website
 * (Api\App\BudgetController roept die aan) en geeft de hele begroting terug.
 */

use App\Http\Controllers\Api\App\BudgetController as AppBudgetController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-crm')->group(function () {
    Route::get('begrotingen', [AppBudgetController::class, 'index'])->name('budgets.index');
    Route::get('begrotingen/{budget}', [AppBudgetController::class, 'show'])->name('budgets.show');
    Route::patch('begrotingen/{budget}', [AppBudgetController::class, 'update'])->name('budgets.update');
    Route::post('begrotingen/{budget}/definitief', [AppBudgetController::class, 'finalise'])->name('budgets.finalise');
    Route::post('begrotingen/{budget}/heropenen', [AppBudgetController::class, 'reopen'])->name('budgets.reopen');

    // Indeling: hoofdstukken en paragrafen.
    Route::post('begrotingen/{budget}/hoofdstukken', [AppBudgetController::class, 'storeChapter'])->name('budget-chapters.store');
    Route::delete('begrotingshoofdstukken/{chapter}', [AppBudgetController::class, 'destroyChapter'])->name('budget-chapters.destroy');
    Route::post('begrotingshoofdstukken/{chapter}/paragrafen', [AppBudgetController::class, 'storeParagraph'])->name('budget-paragraphs.store');

    // Regels.
    Route::post('begrotingsparagrafen/{paragraph}/regels', [AppBudgetController::class, 'storeLine'])->name('budget-lines.store');
    Route::patch('begrotingsregels/{line}', [AppBudgetController::class, 'updateLine'])->name('budget-lines.update');
    Route::delete('begrotingsregels/{line}', [AppBudgetController::class, 'destroyLine'])->name('budget-lines.destroy');

    // Staart, opties en termijnen.
    Route::post('begrotingen/{budget}/staart', [AppBudgetController::class, 'storeMarkup'])->name('budget-markups.store');
    Route::patch('begrotingsstaart/{markup}', [AppBudgetController::class, 'updateMarkup'])->name('budget-markups.update');
    Route::delete('begrotingsstaart/{markup}', [AppBudgetController::class, 'destroyMarkup'])->name('budget-markups.destroy');
    Route::post('begrotingen/{budget}/opties', [AppBudgetController::class, 'storeOption'])->name('budget-options.store');
    Route::patch('begrotingsopties/{option}', [AppBudgetController::class, 'updateOption'])->name('budget-options.update');
    Route::delete('begrotingsopties/{option}', [AppBudgetController::class, 'destroyOption'])->name('budget-options.destroy');
    Route::post('begrotingen/{budget}/termijnen', [AppBudgetController::class, 'storeTerm'])->name('budget-terms.store');
    Route::patch('begrotingstermijnen/{term}', [AppBudgetController::class, 'updateTerm'])->name('budget-terms.update');
    Route::delete('begrotingstermijnen/{term}', [AppBudgetController::class, 'destroyTerm'])->name('budget-terms.destroy');
});
