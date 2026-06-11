<?php

namespace Tests\Feature\Manage;

use App\Models\Area;
use App\Models\Event;
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
