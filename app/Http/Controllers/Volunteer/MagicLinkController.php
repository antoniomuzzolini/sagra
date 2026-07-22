<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\MagicLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MagicLinkController extends Controller
{
    public function consume(Request $request, string $token)
    {
        $link = MagicLink::findByToken($token);
        $person = $link?->person;
        $current = $request->user();

        if ($link === null || $person === null || $link->isExpired()) {
            return $this->invalid($request, 'unknown');
        }

        if ($link->isUsed()) {
            // Re-tapping an old link on the device that consumed it is
            // harmless: just go home. Anywhere else, it is dead (D17).
            return $current?->is($person)
                ? redirect()->route('volunteer.home')
                : $this->invalid($request, 'used');
        }

        // The device already belongs to someone else: ask, never hijack
        // (D17 — think shared family phones). The link stays unused.
        if ($current && ! $current->is($person)) {
            return Inertia::render('Volunteer/SwitchIdentity', [
                'currentName' => $current->name,
                'newName' => $person->name,
                'token' => $token,
            ]);
        }

        $this->login($request, $link);

        return redirect()->route('volunteer.home');
    }

    /**
     * Explicit confirmation from the SwitchIdentity page: hand the
     * device over to the person the link belongs to.
     */
    public function switch(Request $request, string $token): RedirectResponse
    {
        $link = MagicLink::findByToken($token);

        abort_if($link === null || $link->person === null || $link->isExpired() || $link->isUsed(), 404);

        $this->login($request, $link);

        return redirect()->route('volunteer.home');
    }

    private function login(Request $request, MagicLink $link): void
    {
        $link->consume();

        // Remember the session: a volunteer taps the link once and
        // stays in, no password and no repeated logins (D6).
        Auth::login($link->person, remember: true);
        $request->session()->regenerate();
    }

    private function invalid(Request $request, string $reason)
    {
        return Inertia::render('Volunteer/LinkInvalid', ['reason' => $reason])
            ->toResponse($request)
            ->setStatusCode(404);
    }
}
