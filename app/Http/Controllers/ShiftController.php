<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ShiftController extends Controller
{
    public function store(Request $request, Area $area): RedirectResponse
    {
        $this->authorizeTenant($request, $area);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'needed_people' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'date.required' => 'Indica la data del turno.',
            'start_time.required' => 'Indica l\'ora di inizio.',
            'end_time.required' => 'Indica l\'ora di fine.',
            'needed_people.required' => 'Indica quante persone servono.',
            'needed_people.min' => 'Serve almeno una persona.',
        ]);

        $startsAt = Carbon::parse($data['date'].' '.$data['start_time']);
        $endsAt = Carbon::parse($data['date'].' '.$data['end_time']);

        // A shift ending past midnight (e.g. bar 20:00–01:00) rolls
        // over to the next day instead of failing validation.
        if ($endsAt->lte($startsAt)) {
            $endsAt->addDay();
        }

        $area->shifts()->create([
            'tenant_id' => $area->tenant_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'needed_people' => $data['needed_people'],
            'notes' => $data['notes'] ?? null,
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
