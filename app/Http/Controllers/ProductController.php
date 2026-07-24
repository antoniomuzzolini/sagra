<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\CurrentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The listino (Ordini/Cassa): per-event products, defined by the organizer.
 */
class ProductController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $current = CurrentEvent::resolve($request->user(), $request->session()->get('current_event_id'));
        abort_if($current === null, 404);

        Product::create([
            'tenant_id' => $request->user()->tenant_id,
            'event_id' => $current->id,
            ...$this->validated($request, $current->id),
        ]);

        return back();
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeTenant($request, $product);

        $product->update($this->validated($request, $product->event_id));

        return back();
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeTenant($request, $product);

        // Past order lines keep their snapshot; the product_id link goes null.
        $product->delete();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $eventId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('event_id', $eventId)],
            'sub_area_id' => ['nullable', Rule::exists('sub_areas', 'id')->where('area_id', $request->input('area_id'))],
            'active' => ['boolean'],
        ], [
            'name.required' => 'Il nome del prodotto è obbligatorio.',
            'price.required' => 'Indica il prezzo.',
        ]);
    }
}
