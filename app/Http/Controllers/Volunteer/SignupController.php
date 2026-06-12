<?php

namespace App\Http\Controllers\Volunteer;

use App\Enums\SignupStatus;
use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    /**
     * Area managers moderate signups of their areas (D6: they work
     * through the same magic link access, no password account needed).
     */
    public function moderate(Request $request, ShiftSignup $signup): RedirectResponse
    {
        $person = $this->authorizeManager($request, $signup);

        $data = $request->validate([
            'status' => ['required', Rule::enum(SignupStatus::class)],
        ]);

        $status = SignupStatus::from($data['status']);

        $signup->update([
            'status' => $status,
            'assigned_at' => $status === SignupStatus::Assigned ? now() : null,
            'assigned_by' => null, // assigned by a person, not by a user account
            'substitution_requested_at' => null,
        ]);

        return back();
    }

    public function remove(Request $request, ShiftSignup $signup): RedirectResponse
    {
        $this->authorizeManager($request, $signup);

        $signup->delete();

        return back();
    }

    private function authorizeManager(Request $request, ShiftSignup $signup): Person
    {
        $person = $request->user('volunteer');

        abort_unless(
            $signup->tenant_id === $person->tenant_id
                && $person->managesArea($signup->shift->area_id),
            404,
        );

        return $person;
    }
}
