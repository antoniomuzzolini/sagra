<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\SubArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sub-reparti of an area (D21). Defined by the organizer alongside the
 * areas; a light subdivision that shifts can belong to.
 */
class SubAreaController extends Controller
{
    public function store(Request $request, Area $area): RedirectResponse
    {
        $this->authorizeTenant($request, $area);

        $area->subAreas()->create([
            'tenant_id' => $area->tenant_id,
            ...$this->validated($request),
        ]);

        return back();
    }

    public function update(Request $request, SubArea $subArea): RedirectResponse
    {
        $this->authorizeTenant($request, $subArea);

        $subArea->update($this->validated($request));

        return back();
    }

    public function destroy(Request $request, SubArea $subArea): RedirectResponse
    {
        $this->authorizeTenant($request, $subArea);

        // Shifts fall back to area-level (sub_area_id → null) by the FK.
        $subArea->delete();

        return back();
    }

    /**
     * @return array{name: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Il nome del sotto-reparto è obbligatorio.',
        ]);
    }
}
