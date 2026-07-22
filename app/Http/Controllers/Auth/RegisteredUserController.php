<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'association' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:people,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'association.required' => 'Il nome dell\'associazione è obbligatorio.',
        ]);

        // Registering creates the association (tenant) and its organizer —
        // one identity in `people`, with a password and the organizer flag (D19).
        $person = DB::transaction(function () use ($request) {
            $tenant = Tenant::create([
                'name' => $request->association,
                'slug' => $this->uniqueSlug($request->association),
            ]);

            return Person::create([
                'tenant_id' => $tenant->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_organizer' => true,
            ]);
        });

        event(new Registered($person));

        Auth::login($person);

        return to_route('dashboard');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;

        for ($i = 2; Tenant::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
