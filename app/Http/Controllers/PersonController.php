<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Support\PersonRoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(Request $request): Response
    {
        $people = Person::query()
            ->where('tenant_id', $request->user()->tenant_id)
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
