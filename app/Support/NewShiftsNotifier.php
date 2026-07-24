<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Person;
use App\Notifications\NewShiftsAvailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Fires the "nuovi turni" nudge when shifts open in an area. Off by a
 * tenant setting. Recipients: the area's volunteers by soft membership
 * (D18) — plus the newcomers with no signup history anywhere, who haven't
 * picked an area yet and would otherwise never hear the board is out.
 * Throttled per person/area so a batch of shifts reads as one nudge.
 */
class NewShiftsNotifier
{
    public static function maybeNotify(Area $area, Person $actor): void
    {
        if (! $actor->tenant->setting('notify_new_shifts', true)) {
            return;
        }

        $recipients = Person::query()
            ->where('tenant_id', $area->tenant_id)
            ->where('is_organizer', false)
            ->whereKeyNot($actor->id)
            ->where(fn ($query) => $query
                ->whereHas('signups.shift', fn ($q) => $q->where('area_id', $area->id))
                ->orWhereDoesntHave('signups'))
            ->get()
            // One nudge per person/area every 12 hours, however many shifts
            // get created in the sitting.
            ->filter(fn (Person $person) => Cache::add("new-shifts:{$area->id}:{$person->id}", true, now()->addHours(12)));

        Notification::send($recipients, new NewShiftsAvailable($area));
    }
}
