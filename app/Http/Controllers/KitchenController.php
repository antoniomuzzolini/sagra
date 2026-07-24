<?php

namespace App\Http\Controllers;

use App\Enums\OrderItemStatus;
use App\Models\Area;
use App\Models\OrderItem;
use App\Support\CurrentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comande / KDS (Ordini/Cassa slice B): the kitchen screen. Order lines of the
 * current event, scoped to the areas the person runs (organizer: all) and
 * optionally narrowed to one sub-area — one screen per reparto. Lines move
 * pending → ready → served.
 */
class KitchenController extends Controller
{
    public function index(Request $request): Response
    {
        $person = $request->user();
        $current = CurrentEvent::resolve($person, $request->session()->get('current_event_id'));
        $areaIds = $this->managedAreaIds($request, $current);

        $areas = Area::query()->whereIn('id', $areaIds)->with('subAreas')->orderBy('name')->get();

        // Which screen: an area (default: the first) and optionally a sub-area.
        $areaId = (int) $request->integer('area') ?: $areas->first()?->id;
        abort_unless($areaId === null || $areaIds->contains($areaId), 404);
        $subAreaId = $request->integer('sub_area') ?: null;

        $items = $areaId
            ? OrderItem::query()
                ->where('area_id', $areaId)
                ->when($subAreaId, fn ($q) => $q->where('sub_area_id', $subAreaId))
                ->whereIn('status', [OrderItemStatus::Pending, OrderItemStatus::Ready])
                ->whereHas('order', fn ($q) => $q->where('event_id', $current->id))
                ->with('order:id,number,created_at')
                ->orderBy('id')
                ->get()
            : collect();

        return Inertia::render('Manage/Comande', [
            'event' => $current ? ['id' => $current->id, 'name' => $current->name] : null,
            'areas' => $areas->map(fn (Area $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'subAreas' => $a->subAreas->map(fn ($sa) => ['id' => $sa->id, 'name' => $sa->name])->values(),
            ]),
            'areaId' => $areaId,
            'subAreaId' => $subAreaId,
            'items' => $items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'status' => $item->status->value,
                'orderNumber' => $item->order?->number,
                'orderedAt' => $item->order?->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Advance a line: pending → ready → served (or back, if tapped by mistake).
     */
    public function update(Request $request, OrderItem $item): RedirectResponse
    {
        $person = $request->user();

        abort_unless($item->tenant_id === $person->tenant_id, 404);
        abort_unless($item->area_id !== null && $person->managesArea($item->area_id), 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderItemStatus::class)],
        ]);

        $status = OrderItemStatus::from($data['status']);

        $item->update([
            'status' => $status,
            'ready_at' => $status === OrderItemStatus::Ready ? now() : $item->ready_at,
        ]);

        return back();
    }

    /**
     * The current event's areas the person runs (organizer: all).
     *
     * @return Collection<int, int>
     */
    private function managedAreaIds(Request $request, $current): Collection
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
