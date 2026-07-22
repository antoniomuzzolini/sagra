<?php

namespace App\Support;

use App\Enums\Role;
use App\Enums\SignupStatus;
use App\Models\Area;
use App\Models\Person;
use App\Models\Shift;
use Illuminate\Support\Collection;

/**
 * The area manager's scoped data (D18/D19): the areas a person runs, and
 * the coverage / schedule / roster read off them. Shared by the manager's
 * sidebar pages so each view reads the same numbers the same way.
 */
class ManagerScope
{
    /**
     * @return Collection<int, int>
     */
    public static function areaIds(Person $person): Collection
    {
        return $person->managedAreaIds();
    }

    /**
     * The managed areas, eager-loaded for coverage and the schedule.
     *
     * @param  Collection<int, int>  $areaIds
     * @return Collection<int, Area>
     */
    public static function areas(Collection $areaIds): Collection
    {
        if ($areaIds->isEmpty()) {
            return collect();
        }

        return Area::query()
            ->whereIn('id', $areaIds)
            ->with([
                'event.phases' => fn ($query) => $query->orderBy('starts_on'),
                'managerRoles.person' => fn ($query) => $query->withTrashed(),
                'shifts' => fn ($query) => $query
                    ->withCount(['signups as assigned_count' => fn ($q) => $q->where('status', SignupStatus::Assigned)])
                    ->with(['signups' => fn ($q) => $q
                        ->where('status', SignupStatus::Assigned)
                        ->with(['person' => fn ($p) => $p->withTrashed()])])
                    ->orderBy('starts_at'),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Coverage per managed area, for the overview dashboard.
     *
     * @param  Collection<int, Area>  $areas
     */
    public static function overview(Collection $areas): Collection
    {
        return $areas->map(fn (Area $area) => [
            'id' => $area->id,
            'name' => $area->name,
            'family' => $area->family?->value,
            'needed' => $area->shifts->sum('needed_people'),
            'filled' => $area->shifts->sum('assigned_count'),
            'people' => collect($area->shifts->flatMap(fn (Shift $shift) => $shift->signups->map(fn ($signup) => $signup->person->name)))
                ->unique()
                ->values(),
            'managers' => $area->managerRoles->map(fn ($role) => $role->person->name)->values(),
        ])->values();
    }

    /**
     * The schedule (areas + their events' phases) for the timeline.
     *
     * @param  Collection<int, Area>  $areas
     * @return array<string, mixed>
     */
    public static function schedule(Collection $areas): array
    {
        return [
            'areas' => $areas->map(fn (Area $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'family' => $area->family?->value,
                'shifts' => $area->shifts->map(fn (Shift $shift) => [
                    'id' => $shift->id,
                    'starts_at' => $shift->starts_at->toIso8601String(),
                    'ends_at' => $shift->ends_at->toIso8601String(),
                    'needed_people' => $shift->needed_people,
                    'assigned_count' => $shift->assigned_count,
                    'notes' => $shift->notes,
                ]),
            ])->values(),
            'phases' => $areas
                ->flatMap(fn (Area $area) => $area->event->phases)
                ->unique('id')
                ->map(fn ($phase) => [
                    'type' => $phase->type->value,
                    'starts_on' => $phase->starts_on->toDateString(),
                    'ends_on' => $phase->ends_on->toDateString(),
                ])
                ->values(),
        ];
    }

    /**
     * People who belong to the managed areas — through signups there or by
     * co-managing them (D18: soft membership).
     *
     * @param  Collection<int, int>  $areaIds
     */
    public static function roster(Person $person, Collection $areaIds): Collection
    {
        if ($areaIds->isEmpty()) {
            return collect();
        }

        return PersonRoster::rows(
            Person::query()
                ->where('tenant_id', $person->tenant_id)
                ->where('is_organizer', false)
                ->where(fn ($query) => $query
                    ->whereHas('signups.shift', fn ($q) => $q->whereIn('area_id', $areaIds))
                    ->orWhereHas('roles', fn ($q) => $q->where('role', Role::AreaManager)->whereIn('area_id', $areaIds)))
                ->with(PersonRoster::eagerLoads())
                ->orderBy('name')
                ->get()
        );
    }
}
