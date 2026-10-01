<?php

use App\Http\Middleware\CaptureRequestActivity;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureIntsecApiToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('INTSEC_TRUSTED_PROXIES', '')),
        )));
        $middleware->trustProxies(at: $trustedProxies);
        $middleware->append(CaptureRequestActivity::class);

        $middleware->alias([
            'admin' => EnsureAdministrator::class,
            'intsec.api' => EnsureIntsecApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
