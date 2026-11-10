<?php

use App\Http\Middleware\RequireAppUpdate;
use App\Http\Middleware\ThrottlePerRoute;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Elke route een eigen teller bij "throttle:30,1" (zie ThrottlePerRoute).
        $middleware->alias(['throttle' => ThrottlePerRoute::class]);

        // Verplichte app-update (mobile/release.json): serverpagina's in de app. De app-API krijgt hem in routes/api.php.
        $middleware->web(append: [RequireAppUpdate::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
