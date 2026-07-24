<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Notifications\ShiftReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ShiftReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_people_with_email_get_a_reminder_for_tomorrows_shifts()
    {
        Notification::fake();

        $shift = Shift::factory()->create([
            'starts_at' => now()->addHours(20),
            'ends_at' => now()->addHours(24),
        ]);
        $withEmail = Person::factory()->create([
            'tenant_id' => $shift->tenant_id,
            'phone' => null,
            'email' => 'maria@example.com',
        ]);
        $withoutEmail = Person::factory()->create(['tenant_id' => $shift->tenant_id]);
        $onlyAvailable = Person::factory()->create([
            'tenant_id' => $shift->tenant_id,
            'phone' => null,
            'email' => 'piero@example.com',
        ]);

        $reminded = ShiftSignup::factory()->assigned()->for($shift)->for($withEmail)->create();
        ShiftSignup::factory()->assigned()->for($shift)->for($withoutEmail)->create();
        ShiftSignup::factory()->for($shift)->for($onlyAvailable)->create();

        $this->artisan('shifts:send-reminders')->assertSuccessful();

        Notification::assertSentTo($withEmail, ShiftReminder::class);
        Notification::assertNotSentTo([$withoutEmail, $onlyAvailable], ShiftReminder::class);
        $this->assertNotNull($reminded->fresh()->reminded_at);
    }

    public function test_a_phone_only_volunteer_with_push_still_gets_reminded()
    {
        Notification::fake();

        $shift = Shift::factory()->create([
            'starts_at' => now()->addHours(20),
            'ends_at' => now()->addHours(24),
        ]);
        // No email at all (D10 persona: smartphone, magic link), but push on.
        $pushOnly = Person::factory()->create(['tenant_id' => $shift->tenant_id, 'phone' => '+39 333 1112222', 'email' => null]);
        $pushOnly->updatePushSubscription('https://push.test/endpoint', 'key', 'auth');
        ShiftSignup::factory()->assigned()->for($shift)->for($pushOnly)->create();

        $this->artisan('shifts:send-reminders')->assertSuccessful();

        Notification::assertSentTo($pushOnly, ShiftReminder::class);
    }

    public function test_a_volunteer_with_no_channel_is_not_reminded()
    {
        Notification::fake();

        $shift = Shift::factory()->create([
            'starts_at' => now()->addHours(20),
            'ends_at' => now()->addHours(24),
        ]);
        // Neither email nor push: nothing to deliver on, so skip them.
        $unreachable = Person::factory()->create(['tenant_id' => $shift->tenant_id, 'phone' => '+39 333 0000000', 'email' => null]);
        ShiftSignup::factory()->assigned()->for($shift)->for($unreachable)->create();

        $this->artisan('shifts:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_reminders_are_not_sent_twice()
    {
        Notification::fake();

        $shift = Shift::factory()->create([
            'starts_at' => now()->addHours(20),
            'ends_at' => now()->addHours(24),
        ]);
        $person = Person::factory()->create([
            'tenant_id' => $shift->tenant_id,
            'phone' => null,
            'email' => 'maria@example.com',
        ]);
        ShiftSignup::factory()->assigned()->for($shift)->for($person)->create();

        $this->artisan('shifts:send-reminders');
        $this->artisan('shifts:send-reminders');

        Notification::assertSentToTimes($person, ShiftReminder::class, 1);
    }

    public function test_far_future_shifts_are_not_reminded_yet()
    {
        Notification::fake();

        $shift = Shift::factory()->create([
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(4),
        ]);
        $person = Person::factory()->create([
            'tenant_id' => $shift->tenant_id,
            'phone' => null,
            'email' => 'maria@example.com',
        ]);
        ShiftSignup::factory()->assigned()->for($shift)->for($person)->create();

        $this->artisan('shifts:send-reminders');

        Notification::assertNothingSent();
    }
}
