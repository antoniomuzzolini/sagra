<?php

namespace Tests\Feature\Volunteer;

use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubstitutionTest extends TestCase
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

    public function test_a_volunteer_can_request_a_substitution_for_an_assigned_shift()
    {
        $signup = ShiftSignup::factory()->assigned()->for($this->shift)->for($this->person)->create();

        $this->actingAs($this->person, 'volunteer')
            ->post("/me/shifts/{$this->shift->id}/substitution")
            ->assertRedirect();

        $this->assertNotNull($signup->fresh()->substitution_requested_at);
    }

    public function test_a_substitution_cannot_be_requested_for_a_mere_availability()
    {
        $signup = ShiftSignup::factory()->for($this->shift)->for($this->person)->create();

        $this->actingAs($this->person, 'volunteer')->post("/me/shifts/{$this->shift->id}/substitution");

        $this->assertNull($signup->fresh()->substitution_requested_at);
    }

    public function test_a_volunteer_can_cancel_their_substitution_request()
    {
        $signup = ShiftSignup::factory()->assigned()->for($this->shift)->for($this->person)->create([
            'substitution_requested_at' => now(),
        ]);

        $this->actingAs($this->person, 'volunteer')
            ->delete("/me/shifts/{$this->shift->id}/substitution");

        $this->assertNull($signup->fresh()->substitution_requested_at);
    }
}
