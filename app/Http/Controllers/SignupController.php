<?php

namespace App\Http\Controllers;

use App\Enums\SignupStatus;
use App\Models\ShiftSignup;
use App\Notifications\AssignmentConfirmed;
use App\Support\SeatFreedNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SignupController extends Controller
{
    /**
     * Move a signup between available / assigned / declined.
     */
    public function update(Request $request, ShiftSignup $signup): RedirectResponse
    {
        $this->authorizeTenant($request, $signup);

        $data = $request->validate([
            'status' => ['required', Rule::enum(SignupStatus::class)],
        ]);

        $status = SignupStatus::from($data['status']);
        $wasAssigned = $signup->status === SignupStatus::Assigned;

        $signup->update([
            'status' => $status,
            'assigned_at' => $status === SignupStatus::Assigned ? now() : null,
            'assigned_by' => $status === SignupStatus::Assigned ? $request->user()->id : null,
            'substitution_requested_at' => null,
        ]);

        // "Sei confermato" (not when confirming yourself or re-saving).
        if ($status === SignupStatus::Assigned && ! $wasAssigned && ! $signup->person->is($request->user())) {
            $signup->person->notify(new AssignmentConfirmed($signup->shift));
        }

        // Demoting an assignment frees a seat.
        if ($wasAssigned && $status !== SignupStatus::Assigned) {
            SeatFreedNotifier::maybeNotify($signup->shift, $request->user());
        }

        return back();
    }

    /**
     * Remove a person from a shift entirely (e.g. after finding a
     * substitute). The person can sign up again later.
     */
    public function destroy(Request $request, ShiftSignup $signup): RedirectResponse
    {
        $this->authorizeTenant($request, $signup);

        $freedSeat = $signup->status === SignupStatus::Assigned;
        $shift = $signup->shift;

        $signup->delete();

        if ($freedSeat) {
            SeatFreedNotifier::maybeNotify($shift, $request->user());
        }

        return back();
    }
}
