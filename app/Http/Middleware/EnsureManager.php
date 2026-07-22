<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the area-manager sidebar pages (D19): only someone who runs at
 * least one area may open them. Organizers use their own tenant-wide pages.
 */
class EnsureManager
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->managedAreaIds()->isNotEmpty(), 403);

        return $next($request);
    }
}
