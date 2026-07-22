<?php

namespace App\Http\Middleware;

use App\Models\Person;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                // Drives the role-aware sidebar (D19): one shell, the nav
                // items depend on what the person is.
                'role' => $this->role($request->user()),
            ],
            'flash' => [
                'magicLink' => fn () => $request->session()->get('magicLink'),
                'accountInvite' => fn () => $request->session()->get('accountInvite'),
                'recoveryRequested' => fn () => $request->session()->get('recoveryRequested'),
            ],
        ]);
    }

    /**
     * The person's role, orthogonal to identity (D19): organizer (tenant-wide
     * flag), area manager (runs at least one area), or plain volunteer.
     */
    private function role(?Person $person): ?string
    {
        if ($person === null) {
            return null;
        }

        if ($person->isOrganizer()) {
            return 'organizer';
        }

        return $person->managedAreaIds()->isNotEmpty() ? 'manager' : 'volunteer';
    }
}
