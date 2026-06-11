<?php

namespace App\Http\Controllers;

use App\Enums\SignupStatus;
use App\Models\ShiftSignup;
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

        $signup->update([
            'status' => $status,
            'assigned_at' => $status === SignupStatus::Assigned ? now() : null,
            'assigned_by' => $status === SignupStatus::Assigned ? $request->user()->id : null,
            'substitution_requested_at' => null,
        ]);

        return back();
    }

    /**
     * Remove a person from a shift entirely (e.g. after finding a
     * substitute). The person can sign up again later.
     */
    public function destroy(Request $request, ShiftSignup $signup): RedirectResponse
    {
        $this->authorizeTenant($request, $signup);

        $signup->delete();

        return back();
    }
}
