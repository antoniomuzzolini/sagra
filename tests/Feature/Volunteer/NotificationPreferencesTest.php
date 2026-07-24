<?php

namespace Tests\Feature\Volunteer;

use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\Shift;
use App\Models\Tenant;
use App\Notifications\NewShiftsAvailable;
use App\Notifications\SeatFreed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function shift(Tenant $tenant): Shift
    {
        $event = Event::factory()->for($tenant)->create();
        $area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);

        return Shift::factory()->for($area)->create(['tenant_id' => $tenant->id]);
    }

    public function test_notifications_are_on_by_default()
    {
        $person = Person::factory()->create(['email' => 'v@example.com']);

        $this->assertTrue($person->wantsNotification('seat_freed'));
        $this->assertTrue($person->wantsNotification('new_shifts'));
    }

    public function test_a_muted_broadcast_yields_no_channels()
    {
        $tenant = Tenant::factory()->create();
        $person = Person::factory()->for($tenant)->create([
            'email' => 'v@example.com',
            'notification_preferences' => ['seat_freed' => false, 'new_shifts' => false],
        ]);
        $shift = $this->shift($tenant);

        // via() is the per-person gate: muted → nothing is delivered.
        $this->assertSame([], (new SeatFreed($shift))->via($person));
        $this->assertSame([], (new NewShiftsAvailable($shift->area))->via($person));
    }

    public function test_an_unmuted_broadcast_keeps_its_channels()
    {
        $tenant = Tenant::factory()->create();
        $person = Person::factory()->for($tenant)->create([
            'email' => 'v@example.com',
            'notification_preferences' => ['seat_freed' => false, 'new_shifts' => true],
        ]);
        $shift = $this->shift($tenant);

        $this->assertContains('mail', (new NewShiftsAvailable($shift->area))->via($person));
        $this->assertSame([], (new SeatFreed($shift))->via($person));
    }

    public function test_a_volunteer_saves_their_preferences()
    {
        $person = Person::factory()->create(['email' => 'v@example.com']);

        $this->actingAs($person)->put('/me/notifications', [
            'seat_freed' => false,
            'new_shifts' => true,
        ])->assertSessionHasNoErrors();

        $person->refresh();
        $this->assertFalse($person->wantsNotification('seat_freed'));
        $this->assertTrue($person->wantsNotification('new_shifts'));
    }

    public function test_saving_one_preference_leaves_the_others_intact()
    {
        $person = Person::factory()->create([
            'email' => 'v@example.com',
            'notification_preferences' => ['some_future_key' => false],
        ]);

        $this->actingAs($person)->put('/me/notifications', ['seat_freed' => false, 'new_shifts' => true]);

        $person->refresh();
        $this->assertFalse($person->notification_preferences['some_future_key']);
        $this->assertFalse($person->notification_preferences['seat_freed']);
    }
}
