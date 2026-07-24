<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Support\CurrentEvent;
use App\Support\ManagerScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The organizer landing page: the next setup step when the event is not
 * ready yet, the per-area coverage overview (same as the area manager's)
 * once it is running.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Panoramica is scoped to the current event (D20).
        $currentEvent = CurrentEvent::resolve($request->user(), $request->session()->get('current_event_id'));
        $eventAreaIds = $currentEvent ? $currentEvent->areas()->pluck('id') : collect();

        $hasShifts = $eventAreaIds->isNotEmpty() && Shift::query()
            ->whereIn('area_id', $eventAreaIds)
            ->where('starts_at', '>=', now())
            ->exists();

        $nextStep = match (true) {
            $currentEvent === null => 'event',
            $eventAreaIds->isEmpty() => 'areas',
            ! $hasShifts => 'shifts',
            default => null,
        };

        return Inertia::render('Dashboard', [
            'nextStep' => $nextStep,
            'event' => $currentEvent ? ['id' => $currentEvent->id, 'name' => $currentEvent->name] : null,
            // The same per-area coverage the area manager sees, across every
            // area of the current event.
            'areas' => ManagerScope::overview(ManagerScope::areas($eventAreaIds)),
        ]);
    }
}
