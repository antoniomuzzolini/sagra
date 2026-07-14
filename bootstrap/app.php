<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind a TLS-terminating proxy (e.g. a Cloudflare Tunnel for home
        // self-hosting), trust it so HTTPS and secure cookies are detected
        // correctly. Off unless TRUSTED_PROXIES is set (a VPS with FrankenPHP
        // terminates TLS itself and needs nothing here).
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Volunteers have no login page: without a valid session the
        // only way in is a fresh magic link.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('volunteer.*')
            ? route('magic-link.invalid')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
