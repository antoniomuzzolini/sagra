<?php

namespace App\Http\Middleware;

use App\Support\Registration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the sign-up pages (D22): with registration closed they answer 404,
 * as if they didn't exist. The routes stay registered so links and Ziggy
 * don't break; the pages hide the link through `canRegister`.
 */
class EnsureRegistrationOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Registration::isOpen(), 404);

        return $next($request);
    }
}
