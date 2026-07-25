<?php

namespace App\Http\Controllers;

use App\Models\Till;
use App\Support\CurrentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Points of sale (Ordini/Cassa). A till belongs to an area, and that area
 * decides who configures it: its responsabile, or the organizer. A till with
 * no area is the organizer's, like an event-level supply.
 */
class TillController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $current = CurrentEvent::resolve($request->user(), $request->session()->get('current_event_id'));
        abort_if($current === null, 404);

        $data = $this->validated($request, $current->id);
        $this->authorizeArea($request, $data['area_id'] ?? null);

        Till::create([
            'tenant_id' => $request->user()->tenant_id,
            'event_id' => $current->id,
            ...$data,
        ]);

        return back();
    }

    public function update(Request $request, Till $till): RedirectResponse
    {
        $this->authorizeTill($request, $till);

        $data = $this->validated($request, $till->event_id);
        // Moving a till to another area means handing it over: you must run
        // the destination too.
        $this->authorizeArea($request, $data['area_id'] ?? null);

        $till->update($data);

        return back();
    }

    public function destroy(Request $request, Till $till): RedirectResponse
    {
        $this->authorizeTill($request, $till);

        // Past orders keep their history; the till link goes null.
        $till->delete();

        return back();
    }

    /**
     * Compose this till's menu. An empty selection means "sells everything".
     */
    public function updateMenu(Request $request, Till $till): RedirectResponse
    {
        $this->authorizeTill($request, $till);

        $data = $request->validate([
            'products' => ['present', 'array'],
            'products.*' => [Rule::exists('products', 'id')->where('event_id', $till->event_id)],
        ]);

        $till->products()->sync($data['products']);

        return back();
    }

    /**
     * Which till the operator is working at, kept in session like the current
     * event (D20).
     */
    public function select(Request $request): RedirectResponse
    {
        $current = CurrentEvent::resolve($request->user(), $request->session()->get('current_event_id'));

        $data = $request->validate([
            'till_id' => ['nullable', Rule::exists('tills', 'id')->where('event_id', $current?->id)],
        ]);

        $request->session()->put('current_till_id', $data['till_id'] ?? null);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $eventId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('event_id', $eventId)],
        ], [
            'name.required' => 'Il nome della cassa è obbligatorio.',
        ]);
    }

    private function authorizeTill(Request $request, Till $till): void
    {
        abort_unless($till->tenant_id === $request->user()->tenant_id, 404);
        $this->authorizeArea($request, $till->area_id);
    }

    /**
     * The responsabile of the till's area runs it; a till with no area is the
     * organizer's.
     */
    private function authorizeArea(Request $request, ?int $areaId): void
    {
        $person = $request->user();

        abort_unless(
            $areaId === null ? $person->isOrganizer() : $person->managesArea($areaId),
            404,
        );
    }
}
