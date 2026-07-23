<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\Shift;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D20: management views work on one edition at a time, chosen with the
 * current-event selector and kept in the session.
 */
class CurrentEventTest extends TestCase
{
    use RefreshDatabase;

    private function eventWithShift(Tenant $tenant, string $name, string $starts, string $ends, string $shiftDay): Event
    {
        $event = Event::factory()->for($tenant)->create(['name' => $name]);
        $event->phases()->create(['tenant_id' => $tenant->id, 'type' => 'service', 'starts_on' => $starts, 'ends_on' => $ends]);
        $area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id, 'name' => "Cucina {$name}"]);
        Shift::factory()->for($area)->create(['tenant_id' => $tenant->id, 'starts_at' => "{$shiftDay} 18:00", 'ends_at' => "{$shiftDay} 22:00"]);

        return $event;
    }

    public function test_the_calendar_follows_the_selected_event()
    {
        $tenant = Tenant::factory()->create();
        $organizer = Person::factory()->organizer()->for($tenant)->create();
        $past = $this->eventWithShift($tenant, 'Edizione 2024', '2024-07-01', '2024-07-03', '2024-07-02');
        $upcoming = $this->eventWithShift($tenant, 'Edizione 2027', '2027-07-01', '2027-07-03', '2027-07-02');

        // Default: the edition nearest today (the upcoming one).
        $this->actingAs($organizer)->get('/calendar')->assertInertia(
            fn ($page) => $page->has('schedule.areas', 1)->where('schedule.areas.0.name', 'Cucina Edizione 2027')
        );

        // Switch to the past edition; the calendar follows.
        $this->actingAs($organizer)->post('/current-event', ['event_id' => $past->id])->assertSessionHasNoErrors();
        $this->actingAs($organizer)->get('/calendar')->assertInertia(
            fn ($page) => $page->has('schedule.areas', 1)->where('schedule.areas.0.name', 'Cucina Edizione 2024')
        );

        $this->assertNotNull($upcoming); // (both editions exist)
    }

    public function test_the_event_context_lists_the_options_for_an_organizer()
    {
        $tenant = Tenant::factory()->create();
        $organizer = Person::factory()->organizer()->for($tenant)->create();
        $this->eventWithShift($tenant, 'A', '2027-07-01', '2027-07-03', '2027-07-02');
        $this->eventWithShift($tenant, 'B', '2028-07-01', '2028-07-03', '2028-07-02');

        $this->actingAs($organizer)->get('/dashboard')->assertInertia(
            fn ($page) => $page->has('eventContext.options', 2)
        );
    }

    public function test_a_person_cannot_switch_to_an_event_they_cannot_see()
    {
        $organizer = Person::factory()->organizer()->create();
        $foreignEvent = Event::factory()->create(); // another tenant

        $this->actingAs($organizer)->post('/current-event', ['event_id' => $foreignEvent->id])->assertForbidden();
    }

    public function test_a_volunteer_has_no_event_context()
    {
        $volunteer = Person::factory()->create();

        // Volunteers use cross-event pages, so no selector is shared.
        $this->actingAs($volunteer)->get('/me')->assertInertia(
            fn ($page) => $page->where('eventContext', null)
        );
    }
}
