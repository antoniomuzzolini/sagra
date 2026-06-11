<?php

namespace Tests\Feature\Volunteer;

use App\Enums\SignupStatus;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignupFlowTest extends TestCase
{
    use RefreshDatabase;

    private Person $person;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $this->person = Person::factory()->create(['tenant_id' => $tenant->id]);
        $event = Event::factory()->create(['tenant_id' => $tenant->id]);
        $area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);
        $this->shift = Shift::factory()->for($area)->create(['tenant_id' => $tenant->id]);
    }

    private function actingAsVolunteer(): static
    {
        return $this->actingAs($this->person, 'volunteer');
    }

    public function test_the_home_lists_upcoming_shifts_of_the_own_tenant_only()
    {
        Shift::factory()->create(); // other tenant

        $response = $this->actingAsVolunteer()->get('/me');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Volunteer/Home')
            ->has('shifts', 1)
        );
    }

    public function test_a_volunteer_can_declare_availability()
    {
        $this->actingAsVolunteer()
            ->post("/me/shifts/{$this->shift->id}/signup")
            ->assertRedirect();

        $this->assertDatabaseHas('shift_signups', [
            'shift_id' => $this->shift->id,
            'person_id' => $this->person->id,
            'status' => 'available',
        ]);
    }

    public function test_declaring_twice_is_idempotent()
    {
        $this->actingAsVolunteer()->post("/me/shifts/{$this->shift->id}/signup");
        $this->actingAsVolunteer()->post("/me/shifts/{$this->shift->id}/signup");

        $this->assertSame(1, $this->person->signups()->count());
    }

    public function test_a_declined_signup_becomes_available_again()
    {
        ShiftSignup::factory()->for($this->shift)->for($this->person)->create([
            'status' => SignupStatus::Declined,
        ]);

        $this->actingAsVolunteer()->post("/me/shifts/{$this->shift->id}/signup");

        $this->assertSame(SignupStatus::Available, $this->person->signups()->first()->status);
    }

    public function test_an_available_signup_can_be_withdrawn()
    {
        ShiftSignup::factory()->for($this->shift)->for($this->person)->create();

        $this->actingAsVolunteer()->delete("/me/shifts/{$this->shift->id}/signup");

        $this->assertSame(0, $this->person->signups()->count());
    }

    public function test_an_assigned_signup_cannot_be_withdrawn_by_the_volunteer()
    {
        ShiftSignup::factory()->assigned()->for($this->shift)->for($this->person)->create();

        $this->actingAsVolunteer()->delete("/me/shifts/{$this->shift->id}/signup");

        $this->assertSame(SignupStatus::Assigned, $this->person->signups()->first()->status);
    }

    public function test_a_volunteer_cannot_sign_up_for_another_tenants_shift()
    {
        $foreignShift = Shift::factory()->create();

        $this->actingAsVolunteer()
            ->post("/me/shifts/{$foreignShift->id}/signup")
            ->assertNotFound();
    }
}
