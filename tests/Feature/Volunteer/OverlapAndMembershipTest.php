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

class OverlapAndMembershipTest extends TestCase
{
    use RefreshDatabase;

    private Person $person;

    private Area $kitchen;

    private Area $parking;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $event = Event::factory()->create(['tenant_id' => $tenant->id]);
        $this->kitchen = Area::factory()->for($event)->create(['tenant_id' => $tenant->id, 'name' => 'Cucina']);
        $this->parking = Area::factory()->for($event)->create(['tenant_id' => $tenant->id, 'name' => 'Parcheggi']);
        $this->person = Person::factory()->create(['tenant_id' => $tenant->id]);
    }

    private function shift(Area $area, string $start, string $end): Shift
    {
        return Shift::factory()->for($area)->create([
            'tenant_id' => $area->tenant_id,
            'starts_at' => now()->addDay()->setTimeFromTimeString($start),
            'ends_at' => now()->addDay()->setTimeFromTimeString($end),
        ]);
    }

    public function test_overlapping_shifts_are_flagged_but_not_blocked()
    {
        $assigned = $this->shift($this->kitchen, '18:00', '22:00');
        ShiftSignup::factory()->for($assigned)->for($this->person)->create(['status' => SignupStatus::Assigned]);

        $overlapping = $this->shift($this->parking, '20:00', '23:00');
        $contiguous = $this->shift($this->parking, '22:00', '23:30');

        $response = $this->actingAs($this->person)->get('/me');

        $shifts = collect($response->inertiaPage()['props']['shifts']);
        $this->assertStringContainsString('Cucina', $shifts->firstWhere('id', $overlapping->id)['myOverlap']);
        $this->assertNull($shifts->firstWhere('id', $contiguous->id)['myOverlap']);

        // Allowed, but flagged: signing up still works.
        $this->actingAs($this->person)
            ->post("/me/shifts/{$overlapping->id}/signup")
            ->assertRedirect();
    }

    public function test_managers_see_overlaps_of_the_people_they_confirm()
    {
        $manager = Person::factory()->create(['tenant_id' => $this->kitchen->tenant_id]);
        PersonRole::factory()->for($manager)->for($this->kitchen->event)->create([
            'tenant_id' => $this->kitchen->tenant_id,
            'role' => Role::AreaManager,
            'area_id' => $this->kitchen->id,
        ]);

        $kitchenShift = $this->shift($this->kitchen, '18:00', '22:00');
        ShiftSignup::factory()->for($kitchenShift)->for($this->person)->create(['status' => SignupStatus::Available]);

        $elsewhere = $this->shift($this->parking, '20:00', '23:00');
        ShiftSignup::factory()->for($elsewhere)->for($this->person)->create(['status' => SignupStatus::Assigned]);

        $response = $this->actingAs($manager)->get('/me');

        $shifts = collect($response->inertiaPage()['props']['shifts']);
        $signup = collect($shifts->firstWhere('id', $kitchenShift->id)['signups'])
            ->firstWhere('personId', $this->person->id);

        $this->assertStringContainsString('Parcheggi', $signup['overlapsWith']);
    }

    public function test_my_areas_are_derived_from_signup_history()
    {
        $kitchenShift = $this->shift($this->kitchen, '18:00', '22:00');
        ShiftSignup::factory()->for($kitchenShift)->for($this->person)->create(['status' => SignupStatus::Assigned]);
        $otherKitchenShift = $this->shift($this->kitchen, '12:00', '15:00');
        $parkingShift = $this->shift($this->parking, '12:00', '15:00');

        $response = $this->actingAs($this->person)->get('/me');

        $shifts = collect($response->inertiaPage()['props']['shifts']);
        $this->assertTrue($shifts->firstWhere('id', $otherKitchenShift->id)['isMyArea']);
        $this->assertFalse($shifts->firstWhere('id', $parkingShift->id)['isMyArea']);
    }

    public function test_managed_areas_are_my_areas_even_without_signups()
    {
        PersonRole::factory()->for($this->person)->for($this->parking->event)->create([
            'tenant_id' => $this->parking->tenant_id,
            'role' => Role::AreaManager,
            'area_id' => $this->parking->id,
        ]);
        $parkingShift = $this->shift($this->parking, '18:00', '22:00');
        $kitchenShift = $this->shift($this->kitchen, '18:00', '22:00');

        $response = $this->actingAs($this->person)->get('/me');

        $shifts = collect($response->inertiaPage()['props']['shifts']);
        $this->assertTrue($shifts->firstWhere('id', $parkingShift->id)['isMyArea']);
        $this->assertFalse($shifts->firstWhere('id', $kitchenShift->id)['isMyArea']);
    }

    public function test_a_newcomer_has_no_preferred_areas_yet()
    {
        $this->shift($this->kitchen, '18:00', '22:00');

        $response = $this->actingAs($this->person)->get('/me');

        $shifts = collect($response->inertiaPage()['props']['shifts']);
        $this->assertFalse($shifts->first()['isMyArea']);
    }
}
