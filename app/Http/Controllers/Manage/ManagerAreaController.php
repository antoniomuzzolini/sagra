<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Support\ManagerScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The area manager's scoped pages, rendered inside the same sidebar shell
 * the organizer uses (D19: one navigation, pages gated by role). Read views
 * for now; per-area editing and "i miei turni" still live on /me.
 */
class ManagerAreaController extends Controller
{
    public function overview(Request $request): Response
    {
        $areas = ManagerScope::areas(ManagerScope::areaIds($request->user()));

        return Inertia::render('Manage/Overview', [
            'areas' => ManagerScope::overview($areas),
        ]);
    }

    public function calendar(Request $request): Response
    {
        $areas = ManagerScope::areas(ManagerScope::areaIds($request->user()));

        return Inertia::render('Manage/Calendar', [
            'schedule' => ManagerScope::schedule($areas),
        ]);
    }

    public function people(Request $request): Response
    {
        $areaIds = ManagerScope::areaIds($request->user());

        return Inertia::render('Manage/People', [
            'people' => ManagerScope::roster($request->user(), $areaIds),
        ]);
    }
}
