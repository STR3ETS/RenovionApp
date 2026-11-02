<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\QuoteDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het offertedashboard: verstuurd, geopend, getekend, en wat er nog kan komen.
 */
class QuoteDashboardController extends Controller
{
    public function __invoke(Request $request, QuoteDashboard $dashboard): View
    {
        $period = $request->string('periode')->toString();
        $period = array_key_exists($period, QuoteDashboard::PERIODS) ? $period : 'jaar';

        return view('quotes.dashboard', [
            'data' => $dashboard->build($period, $request->integer('verkoper') ?: null),
            'periods' => QuoteDashboard::PERIODS,
            'sellers' => User::query()
                ->whereIn('role', [UserRole::Sales->value, UserRole::Directie->value, UserRole::Admin->value])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
