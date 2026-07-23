<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Area;
use App\Models\Person;
use App\Models\PersonRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaManagerController extends Controller
{
    public function store(Request $request, Area $area): RedirectResponse
    {
        $this->authorizeTenant($request, $area);

        $data = $request->validate([
            'person_id' => ['required_without:name', 'integer'],
            'name' => ['required_without:person_id', 'string', 'max:255'],
            'phone' => [
                'nullable', 'string', 'max:30',
                Rule::unique('people')->where('tenant_id', $area->tenant_id)->withoutTrashed(),
            ],
        ], [
            'name.required_without' => 'Scegli una persona o scrivi un nome.',
            'phone.unique' => 'C\'è già una persona con questo numero.',
        ]);

        // Pick an existing person, or create one on the spot to put in charge.
        $person = filled($data['name'] ?? null)
            ? Person::create(['tenant_id' => $area->tenant_id, 'name' => $data['name'], 'phone' => $data['phone'] ?? null])
            : Person::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->findOrFail($data['person_id']);

        PersonRole::firstOrCreate([
            'person_id' => $person->id,
            'event_id' => $area->event_id,
            'role' => Role::AreaManager,
            'area_id' => $area->id,
        ], [
            'tenant_id' => $area->tenant_id,
        ]);

        return back();
    }

    public function destroy(Request $request, PersonRole $personRole): RedirectResponse
    {
        $this->authorizeTenant($request, $personRole);

        $personRole->delete();

        return back();
    }
}
