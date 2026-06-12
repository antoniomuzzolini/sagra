<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShiftRequest;
use App\Models\Area;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function store(StoreShiftRequest $request, Area $area): RedirectResponse
    {
        $this->authorizeTenant($request, $area);

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
        $this->authorizeTenant($request, $shift);

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
        $this->authorizeTenant($request, $shift);

        $shift->delete();

        return back();
    }
}
