<?php

/*
 * Portfolio in de app (mobile/app/screens/portfolio.*): lopende projecten met stoplichten. Geladen vanuit
 * routes/api.php binnen de groep voor intern personeel (prefix app/, namen api.app.*). Kantoor, zoals op de website.
 */

use App\Http\Controllers\Api\App\PortfolioController;
use Illuminate\Support\Facades\Route;

Route::get('portfolio', PortfolioController::class)->middleware('can:manage-crm')->name('portfolio');
