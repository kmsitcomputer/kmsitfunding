<?php

use App\Http\Middleware\EnsureIdentityIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireElevatedAssurance;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // IMP-009 — the ONE explicitly authorized CSRF exemption in the
        // Payment Hub (docs/implementation/IMP-009-payment-hub.md
        // "Webhook Security" step 1): a provider cannot supply a
        // platform CSRF token. Scoped to exactly the three provider
        // webhook routes — never generalized to any other route.
        $middleware->validateCsrfTokens(except: [
            'webhooks/payments/tripay',
            'webhooks/payments/xendit',
            'webhooks/payments/stripe',
        ]);

        $middleware->alias([
            'identity.active' => EnsureIdentityIsActive::class,
            'elevated.assurance' => RequireElevatedAssurance::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
