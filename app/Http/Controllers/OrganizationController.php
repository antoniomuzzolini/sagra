<?php

namespace App\Http\Controllers;

use App\Notifications\TestNotification;
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
            // For the self-test button: what would actually be delivered.
            'channels' => [
                'push' => $request->user()->pushSubscriptions()->exists(),
                'email' => filled($request->user()->email),
            ],
            'vapidConfigured' => filled(config('webpush.vapid.public_key')),
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    /**
     * Send yourself a test notification to prove the pipeline works
     * (VAPID keys, queue worker, subscription).
     */
    public function sendTest(Request $request): RedirectResponse
    {
        $request->user()->notify(new TestNotification);

        return back()->with('testNotificationSent', true);
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
