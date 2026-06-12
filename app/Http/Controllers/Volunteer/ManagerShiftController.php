<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShiftRequest;
use App\Models\Area;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Area managers run their own area from the volunteer interface
 * (D18): same magic link access, no password account needed.
 */
class ManagerShiftController extends Controller
{
    public function store(StoreShiftRequest $request, Area $area): RedirectResponse
    {
        $this->authorizeAreaManager($request, $area->tenant_id, $area->id);

        [$startsAt, $endsAt] = $request->times();

        $area->shifts()->create([
            'tenant_id' => $area->tenant_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'needed_people' => $request->validated('needed_people'),
            'notes' => $request->validated('notes'),
        ]);

        return back();
    }

    public function update(StoreShiftRequest $request, Shift $shift): RedirectResponse
    {
        $this->authorizeAreaManager($request, $shift->tenant_id, $shift->area_id);

        [$startsAt, $endsAt] = $request->times();

        $shift->update([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'needed_people' => $request->validated('needed_people'),
            'notes' => $request->validated('notes'),
        ]);

        return back();
    }

    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorizeAreaManager($request, $shift->tenant_id, $shift->area_id);

        $shift->delete();

        return back();
    }

    private function authorizeAreaManager(Request $request, int $tenantId, int $areaId): void
    {
        $person = $request->user('volunteer');

        abort_unless(
            $tenantId === $person->tenant_id && $person->managesArea($areaId),
            404,
        );
    }
}
