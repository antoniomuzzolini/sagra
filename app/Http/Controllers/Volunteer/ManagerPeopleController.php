<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Area managers add the volunteers they recruit in person (D18),
 * getting the magic link right away to forward on WhatsApp.
 */
class ManagerPeopleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $manager = $request->user('volunteer');

        abort_if($manager->managedAreaIds()->isEmpty(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable', 'string', 'max:30',
                Rule::unique('people')->where('tenant_id', $manager->tenant_id)->withoutTrashed(),
            ],
        ], [
            'name.required' => 'Il nome è obbligatorio.',
            'phone.unique' => 'C\'è già una persona con questo numero.',
        ]);

        $person = Person::create([
            'tenant_id' => $manager->tenant_id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);

        $url = route('magic-link.consume', $person->createMagicLink());

        return back()->with('magicLink', [
            'personId' => $person->id,
            'personName' => $person->name,
            'personPhone' => $person->phone,
            'url' => $url,
        ]);
    }
}
