<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Suppliers address book (Forniture module). Tenant-level, shared across
 * editions; managed by the organizer and the area managers.
 */
class SupplierController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->user()->tenant->suppliers()->create($this->validated($request));

        return back();
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorizeTenant($request, $supplier);

        $supplier->update($this->validated($request));

        return back();
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorizeTenant($request, $supplier);

        // Supplies keep their history; the link goes null by the FK.
        $supplier->delete();

        return back();
    }

    /**
     * @return array{name: string, phone: ?string, email: ?string, notes: ?string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'Il nome del fornitore è obbligatorio.',
            'email.email' => 'L\'indirizzo email non è valido.',
        ]);
    }
}
