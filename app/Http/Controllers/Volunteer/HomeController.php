<?php

namespace App\Http\Controllers\Volunteer;

use App\Enums\SignupStatus;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Person;
use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $person = $request->user('volunteer');
        $managedAreaIds = $person->managedAreaIds();

        $shifts = Shift::query()
            ->where('tenant_id', $person->tenant_id)
            ->where('starts_at', '>=', now())
            ->with('area.event')
            ->withCount(['signups as assigned_count' => fn ($query) => $query->where('status', SignupStatus::Assigned)])
            ->with(['signups.person' => fn ($query) => $query->withTrashed()])
            ->orderBy('starts_at')
            ->get();

        // "Your areas" are derived from signup history (D18: soft
        // membership, nothing to declare or administer) — and the areas
        // you run are yours by definition.
        $myAreaIds = $person->signups()->with('shift')->get()
            ->toBase()
            ->map(fn ($signup) => $signup->shift?->area_id)
            ->filter()
            ->merge($managedAreaIds)
            ->unique();

        // Time intervals each person is (possibly) committed to, for
        // overlap warnings (D18: allowed, but flagged).
        $commitments = [];
        foreach ($shifts as $shift) {
            foreach ($shift->signups as $signup) {
                if (in_array($signup->status, [SignupStatus::Available, SignupStatus::Assigned], true)) {
                    $commitments[$signup->person_id][] = $shift;
                }
            }
        }

        $overlapFor = function (int $personId, Shift $shift) use ($commitments): ?string {
            foreach ($commitments[$personId] ?? [] as $other) {
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
            ],
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'tenant' => ['name' => $person->tenant->name],
            // The manager toolkit (D18): only for people running areas.
            'manager' => $managedAreaIds->isEmpty() ? null : [
                'areas' => Area::query()
                    ->whereIn('id', $managedAreaIds)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Area $area) => ['id' => $area->id, 'name' => $area->name]),
                'people' => Person::query()
                    ->where('tenant_id', $person->tenant_id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Person $p) => ['id' => $p->id, 'name' => $p->name]),
                'inviteUrl' => route('join.show', $person->tenant->inviteToken()),
            ],
            'shifts' => $shifts->map(function (Shift $shift) use ($person, $managedAreaIds, $myAreaIds, $overlapFor) {
                $mine = $shift->signups->firstWhere('person_id', $person->id);
                $canModerate = $managedAreaIds->contains($shift->area_id);

                return [
                    'id' => $shift->id,
                    'event' => $shift->area->event->name,
                    'area' => $shift->area->name,
                    'areaId' => $shift->area_id,
                    'starts_at' => $shift->starts_at->toIso8601String(),
                    'ends_at' => $shift->ends_at->toIso8601String(),
                    'needed_people' => $shift->needed_people,
                    'assigned_count' => $shift->assigned_count,
                    'notes' => $shift->notes,
                    'myStatus' => $mine?->status?->value,
                    'mySubstitutionRequested' => $mine?->substitution_requested_at !== null,
                    'isMyArea' => $myAreaIds->contains($shift->area_id),
                    'myOverlap' => $overlapFor($person->id, $shift),
                    'canModerate' => $canModerate,
                    // Signup details stay server-side unless the person manages the area.
                    'signups' => $canModerate
                        ? $shift->signups->map(fn ($signup) => [
                            'id' => $signup->id,
                            'personId' => $signup->person_id,
                            'personName' => $signup->person->name,
                            'personPhone' => $signup->person->phone,
                            'personEmail' => $signup->person->email,
                            'status' => $signup->status->value,
                            'substitutionRequested' => $signup->substitution_requested_at !== null,
                            'overlapsWith' => $overlapFor($signup->person_id, $shift),
                        ])
                        : [],
                ];
            }),
        ]);
    }
}
