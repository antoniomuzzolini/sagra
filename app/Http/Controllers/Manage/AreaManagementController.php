<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Person;
use App\Support\CurrentEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestione aree (D20): the organizer defines the areas of the current event
 * and who runs each one. The edition itself (name, phases) lives under
 * Eventi; here it's just the areas and their responsabili.
 */
class AreaManagementController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $person = $request->user();
        $current = CurrentEvent::resolve($person, $request->session()->get('current_event_id'));

        $areas = $current
            ? Area::query()
                ->where('event_id', $current->id)
                ->with(['managerRoles.person' => fn ($query) => $query->withTrashed()])
                ->orderBy('name')
                ->get()
            : collect();

        return Inertia::render('Manage/Areas', [
            'event' => $current ? ['id' => $current->id, 'name' => $current->name] : null,
            'areas' => $areas->map(fn (Area $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'family' => $area->family?->value,
                'managers' => $area->managerRoles->map(fn ($role) => [
                    'id' => $role->id,
                    'personId' => $role->person_id,
                    'name' => $role->person->name,
                    'phone' => $role->person->phone,
                    'email' => $role->person->email,
                ])->values(),
            ]),
            // People who can be put in charge of an area (not the organizers,
            // who already run everything).
            'people' => Person::query()
                ->where('tenant_id', $person->tenant_id)
                ->where('is_organizer', false)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Person $p) => ['id' => $p->id, 'name' => $p->name]),
        ]);
    }
}
