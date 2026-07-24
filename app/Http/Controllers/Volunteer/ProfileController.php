<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * "Complete the registration" (D16): a self-registered volunteer
     * adds a contact, unlocking reminders and self-service recovery.
     */
    public function updateContact(Request $request): RedirectResponse
    {
        $person = $request->user();

        // Store emails normalised (see Person) and check uniqueness against it.
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        $data = $request->validate([
            'phone' => [
                'nullable', 'string', 'max:30', 'required_without:email',
                Rule::unique('people')->where('tenant_id', $person->tenant_id)->ignore($person)->withoutTrashed(),
            ],
            'email' => [
                'nullable', 'email', 'max:255', 'required_without:phone',
                Rule::unique('people')->where('tenant_id', $person->tenant_id)->ignore($person)->withoutTrashed(),
            ],
        ], [
            'phone.required_without' => 'Lascia almeno un recapito: telefono o email.',
            'email.required_without' => 'Lascia almeno un recapito: telefono o email.',
            'phone.unique' => 'Questo numero è già registrato.',
            'email.unique' => 'Questa email è già registrata.',
            'email.email' => 'L\'indirizzo email non è valido.',
        ]);

        $person->update($data);

        return back();
    }

    /**
     * Per-person notification opt-out: the volunteer mutes the broadcast
     * nudges they don't want. A missing key means "on", so we only store
     * what was actually chosen, merged over any existing preferences.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $person = $request->user();

        $data = $request->validate([
            'seat_freed' => ['required', 'boolean'],
            'new_shifts' => ['required', 'boolean'],
        ]);

        $person->update([
            'notification_preferences' => array_merge($person->notification_preferences ?? [], $data),
        ]);

        return back();
    }
}
