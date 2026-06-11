<?php

namespace App\Http\Controllers\Volunteer;

use App\Enums\SignupStatus;
use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $person = $request->user('volunteer');

        $shifts = Shift::query()
            ->where('tenant_id', $person->tenant_id)
            ->where('starts_at', '>=', now())
            ->with('area.event')
            ->withCount(['signups as assigned_count' => fn ($query) => $query->where('status', SignupStatus::Assigned)])
            ->with(['signups' => fn ($query) => $query->where('person_id', $person->id)])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('Volunteer/Home', [
            'person' => ['name' => $person->name],
            'tenant' => ['name' => $person->tenant->name],
            'shifts' => $shifts->map(fn (Shift $shift) => [
                'id' => $shift->id,
                'event' => $shift->area->event->name,
                'area' => $shift->area->name,
                'starts_at' => $shift->starts_at->toIso8601String(),
                'ends_at' => $shift->ends_at->toIso8601String(),
                'needed_people' => $shift->needed_people,
                'assigned_count' => $shift->assigned_count,
                'notes' => $shift->notes,
                'myStatus' => $shift->signups->first()?->status?->value,
            ]),
        ]);
    }
}
