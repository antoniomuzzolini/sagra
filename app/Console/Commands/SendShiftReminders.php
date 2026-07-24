<?php

namespace App\Console\Commands;

use App\Enums\SignupStatus;
use App\Models\ShiftSignup;
use App\Notifications\ShiftReminder;
use Illuminate\Console\Command;

class SendShiftReminders extends Command
{
    protected $signature = 'shifts:send-reminders';

    protected $description = 'Send a reminder to people assigned to shifts starting within the next 24 hours';

    public function handle(): int
    {
        $signups = ShiftSignup::query()
            ->where('status', SignupStatus::Assigned)
            ->whereNull('reminded_at')
            ->whereHas('shift', fn ($query) => $query->whereBetween('starts_at', [now(), now()->addDay()]))
            // Reach anyone with a channel: push is the primary one (D10), so a
            // phone-only volunteer with a subscription must get the reminder
            // too — not just those who left an email.
            ->whereHas('person', fn ($query) => $query
                ->whereNotNull('email')
                ->orWhereHas('pushSubscriptions'))
            ->with(['shift.area.event', 'person'])
            ->get();

        foreach ($signups as $signup) {
            $signup->person->notify(new ShiftReminder($signup->shift));
            $signup->update(['reminded_at' => now()]);
        }

        $this->info("Sent {$signups->count()} reminders.");

        return self::SUCCESS;
    }
}
