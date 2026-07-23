<?php

namespace App\Http\Controllers;

use App\Support\CurrentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the current event (D20). Kept in the session so it sticks across
 * pages; only events the person may see are accepted.
 */
class EventContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['event_id' => ['required', 'integer']]);

        $allowed = CurrentEvent::options($request->user())->pluck('id');
        abort_unless($allowed->contains($data['event_id']), 403);

        $request->session()->put('current_event_id', $data['event_id']);

        return back();
    }
}
