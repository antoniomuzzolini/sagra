<?php

namespace App\Http\Controllers;

use App\Enums\AreaFamily;
use App\Models\Area;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeTenant($request, $event);

        $event->areas()->create([
            ...$this->validateArea($request),
            'tenant_id' => $event->tenant_id,
        ]);

        return back();
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $this->authorizeTenant($request, $area);

        $area->update($this->validateArea($request));

        return back();
    }

    public function destroy(Request $request, Area $area): RedirectResponse
    {
        $this->authorizeTenant($request, $area);

        $area->delete();

        return back();
    }

    private function validateArea(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'family' => ['nullable', Rule::enum(AreaFamily::class)],
        ], [
            'name.required' => 'Il nome dell\'area è obbligatorio.',
        ]);
    }
}
