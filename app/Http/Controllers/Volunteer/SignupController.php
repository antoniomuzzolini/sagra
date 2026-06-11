<?php

namespace App\Http\Controllers\Volunteer;

use App\Enums\SignupStatus;
use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SignupController extends Controller
{
    /**
     * Declare availability for a shift. Idempotent: tapping twice is
     * fine, and a previously declined signup becomes available again.
     */
    public function store(Request $request, Shift $shift): RedirectResponse
    {
        $person = $request->user('volunteer');

        abort_unless($shift->tenant_id === $person->tenant_id, 404);

        $signup = $person->signups()->firstOrCreate(
            ['shift_id' => $shift->id],
            ['tenant_id' => $person->tenant_id, 'status' => SignupStatus::Available],
        );

        if ($signup->status === SignupStatus::Declined) {
            $signup->update(['status' => SignupStatus::Available]);
        }

        return back();
    }

    /**
     * Withdraw a not-yet-confirmed availability. Assigned shifts can't
     * be dropped here: that's a substitution, handled by the organizer.
     */
    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        $person = $request->user('volunteer');

        abort_unless($shift->tenant_id === $person->tenant_id, 404);

        $person->signups()
            ->where('shift_id', $shift->id)
            ->where('status', SignupStatus::Available)
            ->delete();

        return back();
    }

    /**
     * Ask the organizer to find a substitute for an assigned shift.
     */
    public function requestSubstitution(Request $request, Shift $shift): RedirectResponse
    {
        $person = $request->user('volunteer');

        abort_unless($shift->tenant_id === $person->tenant_id, 404);

        $person->signups()
            ->where('shift_id', $shift->id)
            ->where('status', SignupStatus::Assigned)
            ->update(['substitution_requested_at' => now()]);

        return back();
    }

    public function cancelSubstitution(Request $request, Shift $shift): RedirectResponse
    {
        $person = $request->user('volunteer');

        abort_unless($shift->tenant_id === $person->tenant_id, 404);

        $person->signups()
            ->where('shift_id', $shift->id)
            ->update(['substitution_requested_at' => null]);

        return back();
    }
}
