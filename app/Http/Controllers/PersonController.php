<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Support\PersonRoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(Request $request): Response
    {
        // The roster is the volunteers and area managers; the organizer holds
        // the account but isn't a row in the list they administer (D19).
        $people = Person::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_organizer', false)
            ->with(PersonRoster::eagerLoads())
            ->orderBy('name')
            ->get();

        return Inertia::render('People/Index', [
            'people' => PersonRoster::rows($people),
            'inviteUrl' => route('join.show', $request->user()->tenant->inviteToken()),
        ]);
    }

    /**
     * Rotate the tenant invite link (D16), e.g. after it leaked
     * outside the volunteer group. People already in are unaffected.
     */
    public function regenerateInvite(Request $request): RedirectResponse
    {
        $request->user()->tenant->regenerateInviteToken();

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        Person::create([
            ...$this->validatePerson($request),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return back();
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $this->authorizeTenant($request, $person);

        $person->update($this->validatePerson($request, $person));

        return back();
    }

    public function destroy(Request $request, Person $person): RedirectResponse
    {
        $this->authorizeTenant($request, $person);

        $person->magicLinks()->delete();
        $person->delete();

        return back();
    }

    /**
     * Generate (or regenerate, revoking the old one) the person's
     * access link and flash it once to the organizer for sharing.
     */
    public function magicLink(Request $request, Person $person): RedirectResponse
    {
        $this->authorizeTenant($request, $person);

        $url = route('magic-link.consume', $person->createMagicLink());

        // The fresh link answers any pending recovery request.
        $person->forceFill(['link_requested_at' => null])->save();

        return back()->with('magicLink', [
            'personId' => $person->id,
            'personName' => $person->name,
            'personPhone' => $person->phone,
            'url' => $url,
        ]);
    }

    /**
     * Invite a person to hold an account (D19): area managers (and any
     * person the organizer wants to give a password login) get a
     * set-password link. Like magic links, it's returned once for the
     * organizer to hand over by any channel — email, WhatsApp or copy.
     * An account is keyed by email, so one is required.
     */
    public function accountInvite(Request $request, Person $person): RedirectResponse
    {
        $this->authorizeTenant($request, $person);

        $data = $request->validate([
            'email' => [
                $person->email ? 'nullable' : 'required',
                'email', 'max:255',
                Rule::unique('people')->where('tenant_id', $person->tenant_id)->ignore($person)->withoutTrashed(),
            ],
        ], [
            'email.required' => 'Per un account serve un\'email.',
            'email.email' => 'L\'indirizzo email non è valido.',
            'email.unique' => 'C\'è già una persona con questa email.',
        ]);

        if (! empty($data['email'])) {
            $person->update(['email' => $data['email']]);
        }

        $token = Password::broker('people')->createToken($person);
        $url = route('password.reset', ['token' => $token, 'email' => $person->email]);

        return back()->with('accountInvite', [
            'personId' => $person->id,
            'personName' => $person->name,
            'personPhone' => $person->phone,
            'email' => $person->email,
            'url' => $url,
        ]);
    }

    private function validatePerson(Request $request, ?Person $person = null): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable', 'string', 'max:30', 'required_without:email',
                Rule::unique('people')->where('tenant_id', $tenantId)->ignore($person)->withoutTrashed(),
            ],
            'email' => [
                'nullable', 'email', 'max:255', 'required_without:phone',
                Rule::unique('people')->where('tenant_id', $tenantId)->ignore($person)->withoutTrashed(),
            ],
        ], [
            'name.required' => 'Il nome è obbligatorio.',
            'phone.required_without' => 'Serve almeno un contatto: telefono o email.',
            'email.required_without' => 'Serve almeno un contatto: telefono o email.',
            'phone.unique' => 'C\'è già una persona con questo numero.',
            'email.unique' => 'C\'è già una persona con questa email.',
            'email.email' => 'L\'indirizzo email non è valido.',
        ]);
    }
}
