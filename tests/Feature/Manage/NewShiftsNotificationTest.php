<?php

namespace Tests\Feature\Manage;

use App\Enums\SignupStatus;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use App\Notifications\NewShiftsAvailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NewShiftsNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Person $organizer;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->organizer = Person::factory()->organizer()->for($this->tenant)->create();
        $event = Event::factory()->for($this->tenant)->create();
        $this->area = Area::factory()->for($event)->create(['tenant_id' => $this->tenant->id]);
    }

    private function areaVolunteer(): Person
    {
        // A volunteer with history in the area, reachable by email.
        $volunteer = Person::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'storica@example.com']);
        $pastShift = Shift::factory()->for($this->area)->create([
            'tenant_id' => $this->tenant->id,
            'starts_at' => now()->subDays(2)->setTime(18, 0),
        ]);
        ShiftSignup::factory()->for($pastShift)->for($volunteer)->create([
            'tenant_id' => $this->tenant->id,
            'status' => SignupStatus::Assigned,
        ]);

        return $volunteer;
    }

    private function createShift(): void
    {
        $this->actingAs($this->organizer)->post("/areas/{$this->area->id}/shifts", [
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '22:00',
            'needed_people' => 4,
        ])->assertRedirect();
    }

    public function test_opening_shifts_notifies_the_areas_volunteers()
    {
        Notification::fake();
        $volunteer = $this->areaVolunteer();

        $this->createShift();

        Notification::assertSentTo($volunteer, NewShiftsAvailable::class);
    }

    public function test_a_newcomer_with_no_signups_is_also_notified()
    {
        Notification::fake();
        $newcomer = Person::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'nuovo@example.com']);

        $this->createShift();

        Notification::assertSentTo($newcomer, NewShiftsAvailable::class);
    }

    public function test_a_volunteer_of_another_area_only_is_not_notified()
    {
        Notification::fake();

        // History in a different area → not soft-member of this area, and not a newcomer.
        $otherArea = Area::factory()->for($this->area->event)->create(['tenant_id' => $this->tenant->id]);
        $stranger = Person::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'altrove@example.com']);
        $otherShift = Shift::factory()->for($otherArea)->create(['tenant_id' => $this->tenant->id, 'starts_at' => now()->subDays(2)]);
        ShiftSignup::factory()->for($otherShift)->for($stranger)->create([
            'tenant_id' => $this->tenant->id,
            'status' => SignupStatus::Assigned,
        ]);

        $this->createShift();

        Notification::assertNotSentTo($stranger, NewShiftsAvailable::class);
    }

    public function test_organizers_and_the_actor_are_not_notified()
    {
        Notification::fake();
        $this->areaVolunteer();

        $this->createShift();

        Notification::assertNotSentTo($this->organizer, NewShiftsAvailable::class);
    }

    public function test_the_throttle_collapses_a_batch_into_one_nudge()
    {
        Notification::fake();
        $volunteer = $this->areaVolunteer();

        $this->createShift();
        $this->createShift();

        Notification::assertSentToTimes($volunteer, NewShiftsAvailable::class, 1);
    }

    public function test_past_shifts_do_not_notify()
    {
        Notification::fake();
        $this->areaVolunteer();

        $this->actingAs($this->organizer)->post("/areas/{$this->area->id}/shifts", [
            'date' => now()->subDays(2)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '22:00',
            'needed_people' => 4,
        ])->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_the_new_shifts_broadcast_can_be_turned_off()
    {
        Notification::fake();
        Cache::flush();
        $this->areaVolunteer();
        $this->tenant->update(['settings' => ['notify_new_shifts' => false]]);

        $this->createShift();

        Notification::assertNothingSent();
    }
}
