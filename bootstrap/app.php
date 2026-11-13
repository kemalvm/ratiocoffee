<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ── Sanctum stateless API tokens ──────────────────────────────────
        $middleware->statefulApi();

        // ── Exclude API routes from CSRF verification ─────────────────────
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // ── Custom ERP middleware aliases ──────────────────────────────────
        $middleware->alias([
            'permission'   => \App\Http\Middleware\CheckPermission::class,
            'log.activity' => \App\Http\Middleware\LogActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();

