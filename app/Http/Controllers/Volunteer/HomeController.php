<?php

namespace App\Http\Controllers\Volunteer;

use App\Enums\SignupStatus;
use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Support\CurrentEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prenotazione turni (D20): the participative page, the same for every role.
 * Your commitments, the shifts still open to book, substitutions. Managing
 * shifts (create/assign/moderate) lives in Gestione turni, not here.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $person = $request->user();

        // Account holders follow the current-event selector like every other
        // page (D20); plain volunteers have no selector and see everything.
        $current = CurrentEvent::resolve($person, $request->session()->get('current_event_id'));

        $shifts = Shift::query()
            ->where('tenant_id', $person->tenant_id)
            ->when($current, fn ($query) => $query->whereHas('area', fn ($q) => $q->where('event_id', $current->id)))
            ->where('starts_at', '>=', now())
            ->with('area.event')
            ->withCount(['signups as assigned_count' => fn ($query) => $query->where('status', SignupStatus::Assigned)])
            // Only my own signup on each shift — enough to show my status; no
            // one else's details reach the participative page.
            ->with(['signups' => fn ($query) => $query->where('person_id', $person->id)])
            ->orderBy('starts_at')
            ->get();

        // "Your areas" (D18: soft membership): where you've worked before, plus
        // the areas you run. Yours float to the top.
        $myAreaIds = $person->signups()->with('shift')->get()
            ->toBase()
            ->map(fn ($signup) => $signup->shift?->area_id)
            ->filter()
            ->merge($person->managedAreaIds())
            ->unique();

        // My own commitments, for overlap warnings (D18: allowed, but flagged).
        $myCommitments = $shifts->filter(fn (Shift $shift) => $shift->signups->contains(
            fn ($signup) => in_array($signup->status, [SignupStatus::Available, SignupStatus::Assigned], true),
        ));

        $overlapFor = function (Shift $shift) use ($myCommitments): ?string {
            foreach ($myCommitments as $other) {
                if ($other->id !== $shift->id && $other->starts_at < $shift->ends_at && $other->ends_at > $shift->starts_at) {
                    return $other->area->name.' · '.$other->starts_at->locale('it')->isoFormat('ddd D/M HH:mm').'–'.$other->ends_at->format('H:i');
                }
            }

            return null;
        };

        return Inertia::render('Volunteer/Home', [
            'person' => [
                'name' => $person->name,
                'phone' => $person->phone,
                'email' => $person->email,
                // Nudge to "complete the registration" (D16): a contact
                // unlocks reminders and self-service recovery.
                'needsContact' => blank($person->phone) && blank($person->email),
                'hasPush' => $person->pushSubscriptions()->exists(),
                // Per-person opt-out for the broadcast nudges (default on).
                'notifications' => [
                    'seat_freed' => $person->wantsNotification('seat_freed'),
                    'new_shifts' => $person->wantsNotification('new_shifts'),
                ],
            ],
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'tenant' => ['name' => $person->tenant->name],
            'shifts' => $shifts->map(function (Shift $shift) use ($myAreaIds, $overlapFor) {
                $mine = $shift->signups->first();

                return [
                    'id' => $shift->id,
                    'event' => $shift->area->event->name,
                    'area' => $shift->area->name,
                    'areaId' => $shift->area_id,
                    'areaFamily' => $shift->area->family?->value,
                    'starts_at' => $shift->starts_at->toIso8601String(),
                    'ends_at' => $shift->ends_at->toIso8601String(),
                    'needed_people' => $shift->needed_people,
                    'assigned_count' => $shift->assigned_count,
                    'notes' => $shift->notes,
                    'myStatus' => $mine?->status?->value,
                    'mySubstitutionRequested' => $mine?->substitution_requested_at !== null,
                    'isMyArea' => $myAreaIds->contains($shift->area_id),
                    'myOverlap' => $overlapFor($shift),
                ];
            }),
        ]);
    }
}
