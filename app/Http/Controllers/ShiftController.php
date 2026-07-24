<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShiftRequest;
use App\Models\Area;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

    /**
     * Copy every shift of one day onto another day of the same area:
     * times, headcount and notes travel, signups don't — the new day
     * starts empty.
     */
    public function replicateDay(Request $request, Area $area): RedirectResponse
    {
        $this->authorizeTenant($request, $area);
        abort_unless($request->user()->managesArea($area->id), 404);

        $data = $request->validate([
            'source_date' => ['required', 'date_format:Y-m-d'],
            'target_date' => ['required', 'date_format:Y-m-d', 'different:source_date'],
        ], [
            'target_date.required' => 'Indica il giorno su cui copiare i turni.',
            'target_date.different' => 'Scegli un giorno diverso da quello di partenza.',
        ]);

        $offset = (int) Carbon::parse($data['source_date'])->startOfDay()
            ->diffInDays(Carbon::parse($data['target_date'])->startOfDay(), false);

        $area->shifts()
            ->whereDate('starts_at', $data['source_date'])
            ->get()
            ->each(fn (Shift $shift) => $area->shifts()->create([
                'tenant_id' => $area->tenant_id,
                'starts_at' => $shift->starts_at->copy()->addDays($offset),
                'ends_at' => $shift->ends_at->copy()->addDays($offset),
                'needed_people' => $shift->needed_people,
                'notes' => $shift->notes,
            ]));

        return back();
    }

    /**
     * Delete every shift of one day in an area (and their signups, via the
     * cascade), e.g. to undo a day added by mistake or a bad replicate.
     */
    public function destroyDay(Request $request, Area $area, string $date): RedirectResponse
    {
        $this->authorizeTenant($request, $area);
        abort_unless($request->user()->managesArea($area->id), 404);

        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, 404);

        $area->shifts()->whereDate('starts_at', $date)->delete();

        return back();
    }

    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorizeTenant($request, $shift);

        $shift->delete();

        return back();
    }
}
