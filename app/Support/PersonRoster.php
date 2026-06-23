<?php

namespace App\Support;

use App\Enums\Role;
use App\Enums\SignupStatus;
use App\Models\Person;
use Illuminate\Support\Collection;

/**
 * Maps people to roster rows shared by the organizer's people page and the
 * area manager's scoped roster — so both read role, areas and status the
 * same way (D18: same app, scoped).
 */
class PersonRoster
{
    /**
     * Eager loads a roster query needs to map rows without N+1.
     */
    public static function eagerLoads(): array
    {
        return [
            'magicLinks',
            'roles.area',
            // Soft membership (D18): areas come from where a person has
            // signed up, not from anything declared.
            'signups' => fn ($query) => $query
                ->whereIn('status', [SignupStatus::Available, SignupStatus::Assigned])
                ->with('shift.area'),
        ];
    }

    /**
     * @param  Collection<int, Person>  $people
     * @return Collection<int, array>
     */
    public static function rows(Collection $people): Collection
    {
        return $people->map(fn (Person $person) => self::row($person))->values();
    }

    public static function row(Person $person): array
    {
        $managerRoles = $person->roles->filter(fn ($role) => $role->role === Role::AreaManager);
        $areas = collect($managerRoles->map(fn ($role) => $role->area?->name))
            ->merge($person->signups->map(fn ($signup) => $signup->shift?->area?->name))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return [
            'id' => $person->id,
            'name' => $person->name,
            'phone' => $person->phone,
            'email' => $person->email,
            'role' => $person->roles->contains(fn ($role) => $role->role === Role::Organizer)
                ? 'organizer'
                : ($managerRoles->isNotEmpty() ? 'manager' : 'volunteer'),
            'areas' => $areas,
            'shiftsCount' => $person->signups->where('status', SignupStatus::Assigned)->count(),
            'hasLink' => $person->magicLinks->isNotEmpty(),
            'linkLastUsedAt' => $person->magicLinks->first()?->last_used_at?->toIso8601String(),
            'linkRequested' => $person->link_requested_at !== null,
        ];
    }
}
