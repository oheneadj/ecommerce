<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration as SentryIntegration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        then: function (): void {
            Route::middleware('web')->group(__DIR__.'/../routes/webhooks.php');
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Payment providers can't obtain a CSRF token — signature
        // verification (HandlePaymentWebhook) is this endpoint's actual
        // authenticity check.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        // Registration and password-reset-request have no throttle option
        // in Fortify's own route file — see the `guest-auth-forms` limiter
        // in App\Providers\FortifyServiceProvider for why this is applied
        // globally (route-name-branching, Limit::none() for everything
        // else) rather than attached to those two routes directly.
        $middleware->appendToGroup('web', 'throttle:guest-auth-forms');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // No-ops entirely when SENTRY_LARAVEL_DSN is unset — see
        // docs/HOWTO-setup-sentry.md.
        SentryIntegration::handles($exceptions);
    })->create();
