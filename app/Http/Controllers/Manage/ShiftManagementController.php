<?php

namespace App\Http\Controllers\Manage;

use App\Enums\SignupStatus;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Person;
use App\Models\Shift;
use App\Support\CurrentEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestione turni (D20): the supply side of the schedule — create/configure
 * shifts, see who's available and assign/moderate — scoped to the current
 * event and the areas the person runs (organizer: all). The demand side
 * ("ci sono", recap, substitutions) lives on the universal /me page.
 */
class ShiftManagementController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $person = $request->user();
        $current = CurrentEvent::resolve($person, $request->session()->get('current_event_id'));

        $areaIds = $current
            ? Area::query()->whereIn('id', $person->managedAreaIds())->where('event_id', $current->id)->pluck('id')
            : collect();

        $shifts = Shift::query()
            ->whereIn('area_id', $areaIds)
            ->with(['area', 'signups.person' => fn ($query) => $query->withTrashed()])
            ->withCount(['signups as assigned_count' => fn ($query) => $query->where('status', SignupStatus::Assigned)])
            ->orderBy('starts_at')
            ->get();

        // Overlap warnings (D18): the intervals each person is committed to
        // across these shifts, flagged so the manager sees clashes.
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

        return Inertia::render('Manage/Shifts', [
            'areas' => Area::query()->whereIn('id', $areaIds)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Area $area) => ['id' => $area->id, 'name' => $area->name]),
            'people' => Person::query()->where('tenant_id', $person->tenant_id)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Person $p) => ['id' => $p->id, 'name' => $p->name]),
            'inviteUrl' => route('join.show', $person->tenant->inviteToken()),
            'shifts' => $shifts->map(fn (Shift $shift) => [
                'id' => $shift->id,
                'areaId' => $shift->area_id,
                'area' => $shift->area->name,
                'starts_at' => $shift->starts_at->toIso8601String(),
                'ends_at' => $shift->ends_at->toIso8601String(),
                'needed_people' => $shift->needed_people,
                'assigned_count' => $shift->assigned_count,
                'notes' => $shift->notes,
                'signups' => $shift->signups->map(fn ($signup) => [
                    'id' => $signup->id,
                    'personId' => $signup->person_id,
                    'personName' => $signup->person->name,
                    'personPhone' => $signup->person->phone,
                    'personEmail' => $signup->person->email,
                    'status' => $signup->status->value,
                    'substitutionRequested' => $signup->substitution_requested_at !== null,
                    'overlapsWith' => $overlapFor($signup->person_id, $shift),
                ]),
            ]),
        ]);
    }
}
