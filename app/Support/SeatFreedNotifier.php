<?php

namespace App\Support;

use App\Enums\SignupStatus;
use App\Models\Person;
use App\Models\Shift;
use App\Notifications\SeatFreed;
use Illuminate\Support\Facades\Notification;

/**
 * Fires the "si è liberato un posto" broadcast when an assigned person
 * leaves a future shift and it drops below its headcount. Off by a tenant
 * setting; recipients are the area's volunteers by soft membership (D18:
 * people who have worked or offered there), excluding whoever is already
 * on the shift and the person who acted.
 */
class SeatFreedNotifier
{
    public static function maybeNotify(Shift $shift, Person $actor): void
    {
        $tenant = $actor->tenant;

        if (! $tenant->setting('notify_seat_freed', true)) {
            return;
        }

        if ($shift->starts_at->isPast()) {
            return;
        }

        $assigned = $shift->signups()->where('status', SignupStatus::Assigned)->count();

        if ($assigned >= $shift->needed_people) {
            return; // still covered, nothing to announce
        }

        $onShiftIds = $shift->signups()->pluck('person_id');

        $recipients = Person::query()
            ->where('tenant_id', $shift->tenant_id)
            ->whereKeyNot($actor->id)
            ->whereNotIn('id', $onShiftIds)
            ->whereHas('signups.shift', fn ($query) => $query->where('area_id', $shift->area_id))
            ->get();

        Notification::send($recipients, new SeatFreed($shift));
    }
}
