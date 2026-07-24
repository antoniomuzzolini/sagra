<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Support\CurrentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cassa/POS (Ordini/Cassa slice A): ring up an order from the event's listino,
 * cash + "segna pagato". Scoped to the current event; any staff can sell. The
 * kitchen screens (comande) come in slice B.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $person = $request->user();
        $current = CurrentEvent::resolve($person, $request->session()->get('current_event_id'));

        $products = $current
            ? Product::query()->where('event_id', $current->id)->where('active', true)
                ->with(['area', 'subArea'])->orderBy('name')->get()
            : collect();

        $orders = $current
            ? Order::query()->where('event_id', $current->id)->with('items')
                ->latest('id')->limit(30)->get()
            : collect();

        return Inertia::render('Manage/Cassa', [
            'event' => $current ? ['id' => $current->id, 'name' => $current->name] : null,
            'canManageListino' => $person->isOrganizer(),
            'areas' => $current
                ? $current->areas()->with('subAreas')->orderBy('name')->get()
                    ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'subAreas' => $a->subAreas->map(fn ($sa) => ['id' => $sa->id, 'name' => $sa->name])->values()])
                : [],
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price,
                'areaId' => $p->area_id,
                'area' => $p->area?->name,
                'subAreaId' => $p->sub_area_id,
            ]),
            'orders' => $orders->map(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'total' => $o->total,
                'paid' => $o->paid,
                'createdAt' => $o->created_at->toIso8601String(),
                'items' => $o->items->map(fn ($i) => ['name' => $i->name, 'quantity' => $i->quantity])->values(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $current = CurrentEvent::resolve($request->user(), $request->session()->get('current_event_id'));
        abort_if($current === null, 404);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('event_id', $current->id)->where('active', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'paid' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'items.required' => 'Aggiungi almeno un prodotto.',
        ]);

        DB::transaction(function () use ($request, $current, $data) {
            $products = Product::query()->whereIn('id', collect($data['items'])->pluck('product_id'))->get()->keyBy('id');

            $order = Order::create([
                'tenant_id' => $request->user()->tenant_id,
                'event_id' => $current->id,
                'number' => (int) Order::where('event_id', $current->id)->max('number') + 1,
                'paid' => $data['paid'] ?? true,
                'payment_method' => 'cash',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
                'total' => 0,
            ]);

            $total = 0;
            foreach ($data['items'] as $line) {
                $product = $products[$line['product_id']];
                $total += (float) $product->price * $line['quantity'];

                $order->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $line['quantity'],
                    'area_id' => $product->area_id,
                    'sub_area_id' => $product->sub_area_id,
                ]);
            }

            $order->update(['total' => $total]);
        });

        return back();
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeTenant($request, $order);

        $order->update($request->validate(['paid' => ['required', 'boolean']]));

        return back();
    }
}
