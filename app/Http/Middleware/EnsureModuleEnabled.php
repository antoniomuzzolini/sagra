<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use App\Support\CurrentEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a module's routes on the current event (D21). Knowing the URL isn't
 * enough: a module switched off answers 404, as if it didn't exist.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $current = CurrentEvent::resolve($request->user(), $request->session()->get('current_event_id'));

        // No event at all: let the page render its own "nessun evento" state.
        if ($current === null) {
            return $next($request);
        }

        abort_unless($current->hasModule(Module::from($module)), 404);

        return $next($request);
    }
}
