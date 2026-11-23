<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Vijf pogingen per minuut per e-mailadres en IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // De app slaat op na elke handeling; ruim genoeg voor vlot werken, niet voor misbruik.
        RateLimiter::for('os-sync', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));
        // Nova en de agents kosten per vraag geld: ruim voor gewoon werk, een grens tegen een vastgelopen script.
        RateLimiter::for('os-ai', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('os-files', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
