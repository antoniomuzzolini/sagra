<?php

namespace App\Http\Controllers;

use App\Enums\SupplyType;
use App\Models\Area;
use App\Models\Supplier;
use App\Models\Supply;
use App\Support\CurrentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Forniture module (first vertical module, D21): what was bought/rented/
 * borrowed for the current edition, plus the tenant's supplier address book.
 * Talks only to the core (aree/sotto-reparti, tenant). Scoped to the current
 * event; area managers see only their own areas, the organizer sees all.
 */
class SupplyController extends Controller
{
    public function index(Request $request): Response
    {
        $person = $request->user();
        $current = CurrentEvent::resolve($person, $request->session()->get('current_event_id'));

        $areaIds = $this->managedEventAreaIds($request, $current);

        $supplies = $current
            ? Supply::query()
                ->where('event_id', $current->id)
                ->when(! $person->isOrganizer(), fn ($q) => $q->whereIn('area_id', $areaIds))
                ->with(['area', 'subArea', 'supplier', 'attachments'])
                ->orderByDesc('acquired_on')
                ->orderByDesc('id')
                ->get()
            : collect();

        return Inertia::render('Manage/Forniture', [
            'event' => $current ? ['id' => $current->id, 'name' => $current->name] : null,
            'areas' => Area::query()->whereIn('id', $areaIds)->with('subAreas')->orderBy('name')->get()
                ->map(fn (Area $area) => [
                    'id' => $area->id,
                    'name' => $area->name,
                    'subAreas' => $area->subAreas->map(fn ($sa) => ['id' => $sa->id, 'name' => $sa->name])->values(),
                ]),
            'suppliers' => $person->tenant->suppliers()->orderBy('name')->get()
                ->map(fn (Supplier $s) => ['id' => $s->id, 'name' => $s->name, 'phone' => $s->phone, 'email' => $s->email, 'notes' => $s->notes]),
            'supplies' => $supplies->map(fn (Supply $supply) => [
                'id' => $supply->id,
                'type' => $supply->type->value,
                'description' => $supply->description,
                'cost' => $supply->cost,
                'acquiredOn' => $supply->acquired_on?->toDateString(),
                'notes' => $supply->notes,
                'areaId' => $supply->area_id,
                'area' => $supply->area?->name,
                'subArea' => $supply->subArea?->name,
                'supplierId' => $supply->supplier_id,
                'supplier' => $supply->supplier?->name,
                'attachments' => $supply->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'url' => route('supply-attachments.download', $a->id),
                ])->values(),
            ]),
            'types' => collect(SupplyType::cases())->map(fn ($t) => $t->value),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $current = CurrentEvent::resolve($request->user(), $request->session()->get('current_event_id'));
        abort_if($current === null, 404);

        $data = $this->validated($request, $current->id);
        $this->authorizeArea($request, $data['area_id'] ?? null);

        Supply::create([
            'tenant_id' => $request->user()->tenant_id,
            'event_id' => $current->id,
            ...$data,
        ]);

        return back();
    }

    public function update(Request $request, Supply $supply): RedirectResponse
    {
        $this->authorizeTenant($request, $supply);
        $this->authorizeArea($request, $supply->area_id);

        $data = $this->validated($request, $supply->event_id);
        $this->authorizeArea($request, $data['area_id'] ?? null);

        $supply->update($data);

        return back();
    }

    public function destroy(Request $request, Supply $supply): RedirectResponse
    {
        $this->authorizeTenant($request, $supply);
        $this->authorizeArea($request, $supply->area_id);

        $supply->delete();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $eventId): array
    {
        return $request->validate([
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('event_id', $eventId)],
            'sub_area_id' => ['nullable', Rule::exists('sub_areas', 'id')->where('area_id', $request->input('area_id'))],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'type' => ['required', Rule::enum(SupplyType::class)],
            'description' => ['required', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'acquired_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'description.required' => 'Indica cosa è stato preso.',
            'type.required' => 'Scegli il tipo (acquisto, noleggio o prestito).',
        ]);
    }

    /**
     * A manager may only touch supplies of areas they run; an event-level
     * supply (no area) is the organizer's.
     */
    private function authorizeArea(Request $request, ?int $areaId): void
    {
        $person = $request->user();

        abort_unless(
            $areaId === null ? $person->isOrganizer() : $person->managesArea($areaId),
            404,
        );
    }

    /**
     * The current event's areas the person runs (organizer: all).
     *
     * @return Collection<int, int>
     */
    private function managedEventAreaIds(Request $request, $current): Collection
    {
        if ($current === null) {
            return collect();
        }

        return Area::query()
            ->whereIn('id', $request->user()->managedAreaIds())
            ->where('event_id', $current->id)
            ->pluck('id');
    }
}
