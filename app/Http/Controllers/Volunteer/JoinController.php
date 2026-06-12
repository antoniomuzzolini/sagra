<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Self-registration through the tenant invite link (D16): the
 * organizer shares one link, volunteers sign themselves up with
 * just a name — no password, no per-person link to deliver.
 */
class JoinController extends Controller
{
    public function show(Request $request, string $token)
    {
        $tenant = Tenant::where('invite_token', $token)->first();

        if ($tenant === null) {
            return Inertia::render('Volunteer/LinkInvalid', ['reason' => 'unknown'])
                ->toResponse($request)
                ->setStatusCode(404);
        }

        return Inertia::render('Volunteer/Join', [
            'tenantName' => $tenant->name,
            'token' => $token,
            // Tell, don't block (D17): the device may legitimately serve
            // more than one volunteer.
            'currentName' => $request->user('volunteer')?->name,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $tenant = Tenant::where('invite_token', $token)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable', 'string', 'max:30',
                Rule::unique('people')->where('tenant_id', $tenant->id)->withoutTrashed(),
            ],
        ], [
            'name.required' => 'Come ti chiami?',
            'phone.unique' => 'Questo numero è già registrato: se è il tuo, chiedi al referente un nuovo link d\'accesso.',
        ]);

        $person = Person::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);

        Auth::guard('volunteer')->login($person, remember: true);
        $request->session()->regenerate();

        return redirect()->route('volunteer.home');
    }
}
