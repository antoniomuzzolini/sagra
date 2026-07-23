<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Support\CurrentEvent;
use App\Support\ManagerScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The area manager's scoped pages, rendered inside the same sidebar shell
 * the organizer uses (D19: one navigation, pages gated by role). Read views
 * for now; per-area editing and "i miei turni" still live on /me. Each page
 * is scoped to the current event (D20).
 */
class ManagerAreaController extends Controller
{
    public function overview(Request $request): Response
    {
        $areas = ManagerScope::areas($this->scopedAreaIds($request));

        return Inertia::render('Manage/Overview', [
            'areas' => ManagerScope::overview($areas),
        ]);
    }

    public function calendar(Request $request): Response
    {
        $areas = ManagerScope::areas($this->scopedAreaIds($request));

        return Inertia::render('Manage/Calendar', [
            'schedule' => ManagerScope::schedule($areas),
        ]);
    }

    public function people(Request $request): Response
    {
        return Inertia::render('Manage/People', [
            'people' => ManagerScope::roster($request->user(), $this->scopedAreaIds($request)),
        ]);
    }

    /**
     * The areas the person manages within the current event (D20).
     *
     * @return Collection<int, int>
     */
    private function scopedAreaIds(Request $request): Collection
    {
        $managed = $request->user()->managedAreaIds();
        $current = CurrentEvent::resolve($request->user(), $request->session()->get('current_event_id'));

        if ($current === null) {
            return $managed;
        }

        return Area::query()
            ->whereIn('id', $managed)
            ->where('event_id', $current->id)
            ->pluck('id');
    }
}
