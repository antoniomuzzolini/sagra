<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\MagicLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MagicLinkController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $link = MagicLink::findActive($token);
        $person = $link?->person;

        if ($person === null) {
            return Inertia::render('Volunteer/LinkInvalid')
                ->toResponse($request)
                ->setStatusCode(404);
        }

        $link->forceFill(['last_used_at' => now()])->save();

        // Remember the session: a volunteer taps the link once and
        // stays in, no password and no repeated logins (D6).
        Auth::guard('volunteer')->login($person, remember: true);
        $request->session()->regenerate();

        return redirect()->route('volunteer.home');
    }
}
