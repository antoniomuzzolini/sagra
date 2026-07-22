<?php

namespace App\Http\Controllers;

use App\Enums\SignupStatus;
use App\Models\Area;
use App\Support\ManagerScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The organizer's tenant-wide calendar: every area's shifts on the same
 * timeline the area manager sees scoped to their own (D19: one navigation,
 * same views, different scope).
 */
class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $areas = Area::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereHas('shifts')
            ->with([
                'event.phases' => fn ($query) => $query->orderBy('starts_on'),
                'shifts' => fn ($query) => $query
                    ->withCount(['signups as assigned_count' => fn ($q) => $q->where('status', SignupStatus::Assigned)])
                    ->orderBy('starts_at'),
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('Manage/Calendar', [
            'schedule' => ManagerScope::schedule($areas),
        ]);
    }
}
