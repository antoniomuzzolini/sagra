<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Area;
use App\Models\Person;
use App\Models\PersonRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AreaManagerController extends Controller
{
    public function store(Request $request, Area $area): RedirectResponse
    {
        $this->authorizeTenant($request, $area);

        $data = $request->validate(['person_id' => ['required', 'integer']]);

        $person = Person::query()
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
