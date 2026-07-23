<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards Gestione turni (D20): the organizer (who runs everything) or anyone
 * holding an area-manager role. Plain volunteers get the participative page
 * instead.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $person = $request->user();

        abort_unless(
            $person !== null && ($person->isOrganizer() || $person->roles()->where('role', Role::AreaManager)->exists()),
            403,
        );

        return $next($request);
    }
}
