<?php

namespace Tests\Feature\Volunteer;

use App\Enums\Role;
use App\Enums\SignupStatus;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerPowersTest extends TestCase
{
    use RefreshDatabase;

    private Person $manager;

    private Area $area;

    private Area $otherArea;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $event = Event::factory()->create(['tenant_id' => $tenant->id]);
        $this->area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);
        $this->otherArea = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);

        $this->manager = Person::factory()->create(['tenant_id' => $tenant->id]);
        PersonRole::factory()->for($this->manager)->for($event)->create([
            'tenant_id' => $tenant->id,
            'role' => Role::AreaManager,
            'area_id' => $this->area->id,
        ]);
    }

    public function test_a_manager_can_create_a_shift_in_their_area()
    {
        $this->actingAs($this->manager)
            ->post("/me/areas/{$this->area->id}/shifts", [
                'date' => '2026-07-04',
                'start_time' => '18:00',
                'end_time' => '22:00',
                'needed_people' => 4,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->area->shifts()->count());
    }

    public function test_a_manager_cannot_create_shifts_elsewhere()
    {
        $this->actingAs($this->manager)
            ->post("/me/areas/{$this->otherArea->id}/shifts", [
                'date' => '2026-07-04',
                'start_time' => '18:00',
                'end_time' => '22:00',
                'needed_people' => 4,
            ])
            ->assertNotFound();

        $this->assertSame(0, Shift::count());
    }

    public function test_a_plain_volunteer_cannot_create_shifts()
    {
        $volunteer = Person::factory()->create(['tenant_id' => $this->area->tenant_id]);

        $this->actingAs($volunteer)
            ->post("/me/areas/{$this->area->id}/shifts", [
                'date' => '2026-07-04',
                'start_time' => '18:00',
                'end_time' => '22:00',
                'needed_people' => 4,
            ])
            ->assertNotFound();
    }

    public function test_a_manager_can_update_any_shift_of_their_area()
    {
        // Created by the organizer, but it lives in the managed area:
        // the manager can reshape it.
        $shift = Shift::factory()->for($this->area)->create(['tenant_id' => $this->area->tenant_id]);

        $this->actingAs($this->manager)->put("/me/shifts/{$shift->id}", [
            'date' => '2026-07-05',
            'start_time' => '19:00',
            'end_time' => '23:00',
            'needed_people' => 6,
        ])->assertSessionHasNoErrors();

        $shift->refresh();
        $this->assertSame('2026-07-05 19:00', $shift->starts_at->format('Y-m-d H:i'));
        $this->assertSame(6, $shift->needed_people);
    }

    public function test_a_manager_cannot_update_shifts_elsewhere()
    {
        $shift = Shift::factory()->for($this->otherArea)->create(['tenant_id' => $this->area->tenant_id]);

        $this->actingAs($this->manager)->put("/me/shifts/{$shift->id}", [
            'date' => '2026-07-05',
            'start_time' => '19:00',
            'end_time' => '23:00',
            'needed_people' => 6,
        ])->assertNotFound();
    }

    public function test_a_manager_can_delete_a_shift_in_their_area_only()
    {
        $own = Shift::factory()->for($this->area)->create(['tenant_id' => $this->area->tenant_id]);
        $foreign = Shift::factory()->for($this->otherArea)->create(['tenant_id' => $this->area->tenant_id]);

        $this->actingAs($this->manager)->delete("/me/shifts/{$own->id}")->assertRedirect();
        $this->actingAs($this->manager)->delete("/me/shifts/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseMissing('shifts', ['id' => $own->id]);
        $this->assertDatabaseHas('shifts', ['id' => $foreign->id]);
    }

    public function test_a_manager_can_replicate_and_delete_a_day_in_their_area_only()
    {
        Shift::factory()->for($this->area)->create([
            'tenant_id' => $this->area->tenant_id,
            'starts_at' => '2026-07-04 18:00',
            'ends_at' => '2026-07-04 22:00',
        ]);

        // Replicate within the managed area works…
        $this->actingAs($this->manager)
            ->post("/areas/{$this->area->id}/shifts/replicate-day", ['source_date' => '2026-07-04', 'target_date' => '2026-07-11'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $this->area->shifts()->whereDate('starts_at', '2026-07-11')->count());

        // …and deleting the copied day too.
        $this->actingAs($this->manager)
            ->delete("/areas/{$this->area->id}/shifts/day/2026-07-11")
            ->assertRedirect();
        $this->assertSame(0, $this->area->shifts()->whereDate('starts_at', '2026-07-11')->count());

        // Another area is out of reach.
        Shift::factory()->for($this->otherArea)->create(['tenant_id' => $this->area->tenant_id, 'starts_at' => '2026-07-04 18:00']);
        $this->actingAs($this->manager)
            ->post("/areas/{$this->otherArea->id}/shifts/replicate-day", ['source_date' => '2026-07-04', 'target_date' => '2026-07-11'])
            ->assertNotFound();
        $this->actingAs($this->manager)
            ->delete("/areas/{$this->otherArea->id}/shifts/day/2026-07-04")
            ->assertNotFound();
    }

    public function test_a_plain_volunteer_cannot_touch_whole_days()
    {
        $volunteer = Person::factory()->create(['tenant_id' => $this->area->tenant_id]);
        Shift::factory()->for($this->area)->create(['tenant_id' => $this->area->tenant_id, 'starts_at' => '2026-07-04 18:00']);

        $this->actingAs($volunteer)
            ->post("/areas/{$this->area->id}/shifts/replicate-day", ['source_date' => '2026-07-04', 'target_date' => '2026-07-11'])
            ->assertNotFound();
        $this->actingAs($volunteer)
            ->delete("/areas/{$this->area->id}/shifts/day/2026-07-04")
            ->assertNotFound();
    }

    public function test_a_manager_can_put_a_person_straight_onto_a_shift()
    {
        $shift = Shift::factory()->for($this->area)->create(['tenant_id' => $this->area->tenant_id]);
        $volunteer = Person::factory()->create(['tenant_id' => $this->area->tenant_id]);

        $this->actingAs($this->manager)
            ->post("/me/shifts/{$shift->id}/assign", ['person_id' => $volunteer->id])
            ->assertRedirect();

        $signup = ShiftSignup::where('shift_id', $shift->id)->where('person_id', $volunteer->id)->first();
        $this->assertSame(SignupStatus::Assigned, $signup->status);
        $this->assertNull($signup->assigned_by);
    }

    public function test_a_manager_can_assign_a_volunteer_created_on_the_spot()
    {
        $shift = Shift::factory()->for($this->area)->create(['tenant_id' => $this->area->tenant_id]);

        $this->actingAs($this->manager)
            ->post("/me/shifts/{$shift->id}/assign", ['name' => 'Nuovo Arrivato'])
            ->assertRedirect();

        $person = Person::where('tenant_id', $this->area->tenant_id)->where('name', 'Nuovo Arrivato')->first();
        $this->assertNotNull($person);
        $signup = ShiftSignup::where('shift_id', $shift->id)->where('person_id', $person->id)->first();
        $this->assertSame(SignupStatus::Assigned, $signup->status);
    }

    public function test_a_new_volunteer_keeps_the_phone_and_rejects_a_duplicate()
    {
        $shift = Shift::factory()->for($this->area)->create(['tenant_id' => $this->area->tenant_id]);
        Person::factory()->create(['tenant_id' => $this->area->tenant_id, 'phone' => '+39 333 0000000']);

        $this->actingAs($this->manager)
            ->post("/me/shifts/{$shift->id}/assign", ['name' => 'Con Telefono', 'phone' => '+39 333 1230000'])
            ->assertRedirect();
        $this->assertSame('+39 333 1230000', Person::where('name', 'Con Telefono')->first()->phone);

        $this->actingAs($this->manager)
            ->post("/me/shifts/{$shift->id}/assign", ['name' => 'Doppione', 'phone' => '+39 333 0000000'])
            ->assertSessionHasErrors('phone');
    }

    public function test_assigning_promotes_an_existing_availability()
    {
        $shift = Shift::factory()->for($this->area)->create(['tenant_id' => $this->area->tenant_id]);
        $volunteer = Person::factory()->create(['tenant_id' => $this->area->tenant_id]);
        ShiftSignup::factory()->for($shift)->for($volunteer)->create(['status' => SignupStatus::Available]);

        $this->actingAs($this->manager)
            ->post("/me/shifts/{$shift->id}/assign", ['person_id' => $volunteer->id]);

        $this->assertSame(1, ShiftSignup::count());
        $this->assertSame(SignupStatus::Assigned, ShiftSignup::first()->status);
    }

    public function test_a_manager_cannot_assign_on_other_areas_or_other_tenants_people()
    {
        $foreignShift = Shift::factory()->for($this->otherArea)->create(['tenant_id' => $this->area->tenant_id]);
        $ownShift = Shift::factory()->for($this->area)->create(['tenant_id' => $this->area->tenant_id]);
        $volunteer = Person::factory()->create(['tenant_id' => $this->area->tenant_id]);
        $foreignPerson = Person::factory()->create();

        $this->actingAs($this->manager)
            ->post("/me/shifts/{$foreignShift->id}/assign", ['person_id' => $volunteer->id])
            ->assertNotFound();
        $this->actingAs($this->manager)
            ->post("/me/shifts/{$ownShift->id}/assign", ['person_id' => $foreignPerson->id])
            ->assertNotFound();

        $this->assertSame(0, ShiftSignup::count());
    }

    public function test_a_manager_can_add_a_volunteer_and_gets_their_link()
    {
        $this->actingAs($this->manager)
            ->post('/me/people', ['name' => 'Luigia Verdi', 'phone' => '+39 333 9876543'])
            ->assertRedirect()
            ->assertSessionHas('magicLink', fn ($flash) => $flash['personName'] === 'Luigia Verdi');

        $person = Person::where('name', 'Luigia Verdi')->first();
        $this->assertSame($this->manager->tenant_id, $person->tenant_id);
        $this->assertSame(1, $person->magicLinks()->count());
    }

    public function test_a_plain_volunteer_cannot_add_people()
    {
        $volunteer = Person::factory()->create(['tenant_id' => $this->area->tenant_id]);

        $this->actingAs($volunteer)
            ->post('/me/people', ['name' => 'Intruso'])
            ->assertNotFound();

        $this->assertNull(Person::where('name', 'Intruso')->first());
    }

    public function test_adding_a_volunteer_with_a_taken_phone_is_rejected()
    {
        Person::factory()->create(['tenant_id' => $this->area->tenant_id, 'phone' => '+39 333 9876543']);

        $this->actingAs($this->manager)
            ->post('/me/people', ['name' => 'Luigia Verdi', 'phone' => '+39 333 9876543'])
            ->assertSessionHasErrors('phone');
    }
}
