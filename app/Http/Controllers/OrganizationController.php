<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization-wide settings (D20): name and a few switches that apply to
 * every event of the tenant. Organizer only.
 */
class OrganizationController extends Controller
{
    public function edit(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        return Inertia::render('Manage/Organization', [
            'organization' => [
                'name' => $tenant->name,
                'notifySeatFreed' => (bool) $tenant->setting('notify_seat_freed', true),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notify_seat_freed' => ['required', 'boolean'],
        ], [
            'name.required' => 'Il nome dell\'organizzazione è obbligatorio.',
        ]);

        $tenant = $request->user()->tenant;

        $tenant->update([
            'name' => $data['name'],
            'settings' => array_merge($tenant->settings ?? [], [
                'notify_seat_freed' => $data['notify_seat_freed'],
            ]),
        ]);

        return back();
    }
}
