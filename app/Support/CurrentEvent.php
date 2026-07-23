<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use Illuminate\Support\Collection;

/**
 * The "current event" context (D20): management views work on one edition at
 * a time. Organizers switch between every edition of their tenant; area
 * managers only between the events they hold a role in.
 */
class CurrentEvent
{
    /**
     * Events the person may select, oldest edition first.
     *
     * @return Collection<int, Event>
     */
    public static function options(Person $person): Collection
    {
        $query = Event::query()->where('tenant_id', $person->tenant_id)->with('phases');

        if (! $person->isOrganizer()) {
            $eventIds = PersonRole::query()
                ->where('person_id', $person->id)
                ->where('role', Role::AreaManager)
                ->distinct()
                ->pluck('event_id');

            $query->whereIn('id', $eventIds);
        }

        return $query->get()
            ->sortBy(fn (Event $event) => $event->startsOn()?->toDateString() ?? '9999-12-31')
            ->values();
    }

    /**
     * The event in context: the session choice if still valid, otherwise the
     * edition nearest to today (D15) — the first still-running or upcoming
     * one, else the most recent past.
     */
    public static function resolve(Person $person, ?int $selectedId): ?Event
    {
        $options = self::options($person);

        if ($selectedId !== null) {
            $chosen = $options->firstWhere('id', $selectedId);

            if ($chosen !== null) {
                return $chosen;
            }
        }

        return $options->first(fn (Event $event) => $event->endsOn() === null || $event->endsOn()->gte(today()))
            ?? $options->last();
    }
}
