<?php

namespace App\Http\Controllers;

use App\Enums\SignupStatus;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Support\CurrentEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The organizer landing page: the next setup step when the event is
 * not ready yet, the work queues once it is running.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        // Panoramica is scoped to the current event (D20); the volunteer roster
        // counts stay tenant-wide, since people cross editions.
        $currentEvent = CurrentEvent::resolve($request->user(), $request->session()->get('current_event_id'));
        $eventAreaIds = $currentEvent ? $currentEvent->areas()->pluck('id') : collect();

        $upcomingShifts = Shift::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('area_id', $eventAreaIds)
            ->where('starts_at', '>=', now())
            ->with('area')
            ->withCount(['signups as assigned_count' => fn ($q) => $q->where('status', SignupStatus::Assigned)])
            ->orderBy('starts_at')
            ->get();

        $uncovered = $upcomingShifts->filter(fn (Shift $shift) => $shift->assigned_count < $shift->needed_people);

        $nextStep = match (true) {
            $currentEvent === null => 'event',
            $eventAreaIds->isEmpty() => 'areas',
            $upcomingShifts->isEmpty() => 'shifts',
            default => null,
        };

        return Inertia::render('Dashboard', [
            'nextStep' => $nextStep,
            'event' => $currentEvent ? ['id' => $currentEvent->id, 'name' => $currentEvent->name] : null,
            'uncoveredCount' => $uncovered->count(),
            'uncoveredShifts' => $uncovered->take(10)->values()->map(fn (Shift $shift) => [
                'id' => $shift->id,
                'eventId' => $shift->area->event_id,
                'area' => $shift->area->name,
                'starts_at' => $shift->starts_at->toIso8601String(),
                'ends_at' => $shift->ends_at->toIso8601String(),
                'missing' => $shift->needed_people - $shift->assigned_count,
            ]),
            'pendingCount' => ShiftSignup::query()
                ->where('tenant_id', $tenantId)
                ->where('status', SignupStatus::Available)
                ->whereHas('shift', fn ($q) => $q->where('starts_at', '>=', now())->whereIn('area_id', $eventAreaIds))
                ->count(),
            'substitutionCount' => ShiftSignup::query()
                ->where('tenant_id', $tenantId)
                ->where('status', SignupStatus::Assigned)
                ->whereNotNull('substitution_requested_at')
                ->whereHas('shift', fn ($q) => $q->where('starts_at', '>=', now())->whereIn('area_id', $eventAreaIds))
                ->count(),
            'linkRequestsCount' => Person::query()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('link_requested_at')
                ->count(),
            // Organizers hold an account but aren't part of the volunteer
            // roster they manage (D19).
            'volunteersCount' => Person::query()
                ->where('tenant_id', $tenantId)
                ->where('is_organizer', false)
                ->count(),
        ]);
    }
}
