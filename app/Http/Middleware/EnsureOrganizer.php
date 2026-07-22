<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the organizer-only management area (D19). With one guard for
 * everyone, the role — not the guard — decides who may run the event:
 * a plain volunteer or an area manager is authenticated but not an
 * organizer, so they get a 403 here.
 */
class EnsureOrganizer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isOrganizer(), 403);

        return $next($request);
    }
}
