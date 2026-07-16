<?php

namespace Tests\Feature\Manage;

use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageEventsTest extends TestCase
{
    use RefreshDatabase;

    private function organizer(): User
    {
        return User::factory()->for(Tenant::factory())->create();
    }

    private function eventInYear(User $user, int $year, string $name): Event
    {
        $event = Event::factory()->create(['tenant_id' => $user->tenant_id, 'name' => $name]);
        $event->phases()->create([
            'tenant_id' => $user->tenant_id,
            'type' => 'service',
            'starts_on' => "{$year}-07-04",
            'ends_on' => "{$year}-07-06",
        ]);

        return $event;
    }

    public function test_the_event_list_defaults_to_the_closest_year()
    {
        $user = $this->organizer();
        $current = $this->eventInYear($user, now()->year, 'Sagra di adesso');
        $this->eventInYear($user, now()->year - 2, 'Sagra di una volta');

        $this->actingAs($user)->get('/events')->assertInertia(
            fn ($page) => $page
                ->component('Events/Index')
                ->where('selectedYear', now()->year)
                ->where('years', [now()->year, now()->year - 2])
                ->has('events', 1)
                ->where('events.0.id', $current->id)
        );
    }

    public function test_the_event_list_can_show_a_past_year()
    {
        $user = $this->organizer();
        $this->eventInYear($user, now()->year, 'Sagra di adesso');
        $past = $this->eventInYear($user, now()->year - 2, 'Sagra di una volta');

        $this->actingAs($user)->get('/events?year='.(now()->year - 2))->assertInertia(
            fn ($page) => $page
                ->where('selectedYear', now()->year - 2)
                ->has('events', 1)
                ->where('events.0.id', $past->id)
        );
    }

    public function test_an_unknown_year_falls_back_to_the_default()
    {
        $user = $this->organizer();
        $current = $this->eventInYear($user, now()->year, 'Sagra di adesso');

        $this->actingAs($user)->get('/events?year=1999')->assertInertia(
            fn ($page) => $page
                ->where('selectedYear', now()->year)
                ->has('events', 1)
                ->where('events.0.id', $current->id)
        );
    }

    public function test_an_organizer_can_create_an_event_with_phases()
    {
        $user = $this->organizer();

        $response = $this->actingAs($user)->post('/events', [
            'name' => 'Sagra 2026',
            'phases' => [
                ['type' => 'preparation', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-03'],
                ['type' => 'service', 'starts_on' => '2026-07-04', 'ends_on' => '2026-07-06'],
            ],
        ]);

        $event = Event::where('tenant_id', $user->tenant_id)->first();

        $response->assertRedirect(route('events.show', $event));
        $this->assertSame(2, $event->phases()->count());
        $this->assertSame('2026-07-01', $event->startsOn()->toDateString());
    }

    public function test_overlapping_phases_are_rejected()
    {
        $this->actingAs($this->organizer())->post('/events', [
            'name' => 'Sagra 2026',
            'phases' => [
                ['type' => 'preparation', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-04'],
                ['type' => 'service', 'starts_on' => '2026-07-04', 'ends_on' => '2026-07-06'],
            ],
        ])->assertSessionHasErrors('phases');

        $this->assertSame(0, Event::count());
    }

    public function test_updating_an_event_replaces_its_phases()
    {
        $user = $this->organizer();
        $event = Event::factory()->create(['tenant_id' => $user->tenant_id]);
        $event->phases()->create([
            'tenant_id' => $user->tenant_id,
            'type' => 'service',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-02',
        ]);

        $this->actingAs($user)->put("/events/{$event->id}", [
            'name' => 'Sagra rinominata',
            'phases' => [
                ['type' => 'preparation', 'starts_on' => '2026-06-28', 'ends_on' => '2026-06-30'],
                ['type' => 'service', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-02'],
            ],
        ])->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertSame('Sagra rinominata', $event->name);
        $this->assertSame(2, $event->phases()->count());
    }

    public function test_the_event_page_shows_areas_and_shifts()
    {
        $user = $this->organizer();
        $event = Event::factory()->create(['tenant_id' => $user->tenant_id]);
        Area::factory()->for($event)->create(['tenant_id' => $user->tenant_id, 'name' => 'Cucina']);

        $this->actingAs($user)->get("/events/{$event->id}")->assertOk();
    }

    public function test_an_organizer_can_add_areas_and_shifts()
    {
        $user = $this->organizer();
        $event = Event::factory()->create(['tenant_id' => $user->tenant_id]);

        $this->actingAs($user)
            ->post("/events/{$event->id}/areas", ['name' => 'Bar', 'family' => 'food_service'])
            ->assertSessionHasNoErrors();

        $area = $event->areas()->first();
        $this->assertSame('Bar', $area->name);

        $this->actingAs($user)->post("/areas/{$area->id}/shifts", [
            'date' => '2026-07-04',
            'start_time' => '18:00',
            'end_time' => '22:00',
            'needed_people' => 4,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $area->shifts()->count());
    }

    public function test_an_organizer_can_update_a_shift()
    {
        $user = $this->organizer();
        $event = Event::factory()->create(['tenant_id' => $user->tenant_id]);
        $area = Area::factory()->for($event)->create(['tenant_id' => $user->tenant_id]);
        $shift = Shift::factory()->for($area)->create(['tenant_id' => $user->tenant_id]);

        $this->actingAs($user)->put("/shifts/{$shift->id}", [
            'date' => '2026-07-05',
            'start_time' => '19:00',
            'end_time' => '23:00',
            'needed_people' => 6,
            'notes' => 'Portare i guanti',
        ])->assertSessionHasNoErrors();

        $shift->refresh();
        $this->assertSame('2026-07-05 19:00', $shift->starts_at->format('Y-m-d H:i'));
        $this->assertSame(6, $shift->needed_people);
        $this->assertSame('Portare i guanti', $shift->notes);
    }

    public function test_a_shift_past_midnight_rolls_to_the_next_day()
    {
        $user = $this->organizer();
        $event = Event::factory()->create(['tenant_id' => $user->tenant_id]);
        $area = Area::factory()->for($event)->create(['tenant_id' => $user->tenant_id]);

        $this->actingAs($user)->post("/areas/{$area->id}/shifts", [
            'date' => '2026-07-04',
            'start_time' => '20:00',
            'end_time' => '01:00',
            'needed_people' => 2,
        ])->assertSessionHasNoErrors();

        $shift = $area->shifts()->first();
        $this->assertSame('2026-07-05', $shift->ends_at->toDateString());
    }

    public function test_replicating_a_day_copies_its_shifts_without_signups()
    {
        $user = $this->organizer();
        $event = Event::factory()->create(['tenant_id' => $user->tenant_id]);
        $area = Area::factory()->for($event)->create(['tenant_id' => $user->tenant_id]);

        $lunch = Shift::factory()->for($area)->create([
            'tenant_id' => $user->tenant_id,
            'starts_at' => '2026-07-04 11:00',
            'ends_at' => '2026-07-04 15:00',
            'needed_people' => 3,
            'notes' => 'Pranzo',
        ]);
        // A bar shift rolling past midnight keeps its overnight shape.
        Shift::factory()->for($area)->create([
            'tenant_id' => $user->tenant_id,
            'starts_at' => '2026-07-04 20:00',
            'ends_at' => '2026-07-05 01:00',
            'needed_people' => 2,
        ]);
        // A signup on the source day must not travel to the copy.
        $person = Person::factory()->create(['tenant_id' => $user->tenant_id]);
        ShiftSignup::factory()->for($lunch)->for($person)->create(['tenant_id' => $user->tenant_id]);
        // A shift on another day stays out of the copy.
        Shift::factory()->for($area)->create([
            'tenant_id' => $user->tenant_id,
            'starts_at' => '2026-07-05 11:00',
            'ends_at' => '2026-07-05 15:00',
        ]);

        $this->actingAs($user)->post("/areas/{$area->id}/shifts/replicate-day", [
            'source_date' => '2026-07-04',
            'target_date' => '2026-07-11',
        ])->assertSessionHasNoErrors();

        $copies = $area->shifts()->whereDate('starts_at', '2026-07-11')->orderBy('starts_at')->get();
        $this->assertCount(2, $copies);
        $this->assertSame('2026-07-11 11:00', $copies[0]->starts_at->format('Y-m-d H:i'));
        $this->assertSame(3, $copies[0]->needed_people);
        $this->assertSame('Pranzo', $copies[0]->notes);
        $this->assertSame('2026-07-12 01:00', $copies[1]->ends_at->format('Y-m-d H:i'));
        $this->assertSame(0, $copies[0]->signups()->count());
    }

    public function test_replicating_onto_the_same_day_is_rejected()
    {
        $user = $this->organizer();
        $event = Event::factory()->create(['tenant_id' => $user->tenant_id]);
        $area = Area::factory()->for($event)->create(['tenant_id' => $user->tenant_id]);

        $this->actingAs($user)->post("/areas/{$area->id}/shifts/replicate-day", [
            'source_date' => '2026-07-04',
            'target_date' => '2026-07-04',
        ])->assertSessionHasErrors('target_date');
    }

    public function test_cross_tenant_event_access_is_a_404()
    {
        $foreignEvent = Event::factory()->create();
        $foreignArea = Area::factory()->create();

        $user = $this->organizer();

        $this->actingAs($user)->get("/events/{$foreignEvent->id}")->assertNotFound();
        $this->actingAs($user)->delete("/events/{$foreignEvent->id}")->assertNotFound();
        $this->actingAs($user)
            ->post("/areas/{$foreignArea->id}/shifts", [
                'date' => '2026-07-04', 'start_time' => '18:00', 'end_time' => '22:00', 'needed_people' => 2,
            ])
            ->assertNotFound();
    }
}
