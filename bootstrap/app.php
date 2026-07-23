<?php

use App\Http\Middleware\EnsureManager;
use App\Http\Middleware\EnsureOrganizer;
use App\Http\Middleware\EnsureStaff;
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

        $middleware->alias([
            'organizer' => EnsureOrganizer::class,
            'manager' => EnsureManager::class,
            'staff' => EnsureStaff::class,
        ]);

        // Simple volunteers have no login page: without a valid session the
        // only way in is a fresh magic link (D6/D17). Account holders
        // (organizers, area managers) land on the login page instead.
        $middleware->redirectGuestsTo(function (Request $request) {
            return $request->routeIs('volunteer.*')
                ? route('magic-link.invalid')
                : route('login');
        });

        // Already-signed-in people who hit a guest-only page (login, register,
        // an invite link) go to their own home — role-aware, so a volunteer
        // isn't bounced onto the organizer dashboard they can't open (D19).
        $middleware->redirectUsersTo(function (Request $request) {
            return $request->user()?->isOrganizer()
                ? route('dashboard')
                : route('volunteer.home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
