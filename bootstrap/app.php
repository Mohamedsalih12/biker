<?php

use App\Http\Middleware\EnsureOtpVerified;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\RecordExpiredSessionLogout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Admin Module Routes
            Route::middleware(['web', 'auth', '2fa', 'force.password.change'])
                ->prefix('admin')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [RecordExpiredSessionLogout::class]);

        // The app is served through Cloudflare, which sets X-Forwarded-For to
        // the real client IP. Trusting '*' would look tidier but would let
        // anyone able to reach the origin directly forge that header, and the
        // IP-keyed rate limiters (login, import) and the audit log would all
        // believe them. So only Cloudflare's published ranges are trusted; with
        // TRUSTED_PROXIES empty nothing is trusted and REMOTE_ADDR is used.
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', ''))
        )));

        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies);
        }

        $middleware->alias([
            'otp.verified' => EnsureOtpVerified::class,
            'force.password.change' => ForcePasswordChange::class,
            '2fa' => PragmaRX\Google2FALaravel\Middleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['aadhaar']);
    })->create();
