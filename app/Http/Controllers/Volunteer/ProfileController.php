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
        $person = $request->user('volunteer');

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
}
