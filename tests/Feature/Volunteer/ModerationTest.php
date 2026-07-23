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

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private Person $manager;

    private Area $area;

    private ShiftSignup $signup;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $event = Event::factory()->create(['tenant_id' => $tenant->id]);
        $this->area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);
        $shift = Shift::factory()->for($this->area)->create(['tenant_id' => $tenant->id]);

        $this->manager = Person::factory()->create(['tenant_id' => $tenant->id]);
        PersonRole::factory()->for($this->manager)->for($event)->create([
            'tenant_id' => $tenant->id,
            'role' => Role::AreaManager,
            'area_id' => $this->area->id,
        ]);

        $volunteer = Person::factory()->create(['tenant_id' => $tenant->id]);
        $this->signup = ShiftSignup::factory()->for($shift)->for($volunteer)->create();
    }

    public function test_an_area_manager_can_confirm_a_signup_in_their_area()
    {
        $this->actingAs($this->manager)
            ->put("/me/signups/{$this->signup->id}", ['status' => 'assigned'])
            ->assertRedirect();

        $this->signup->refresh();
        $this->assertSame(SignupStatus::Assigned, $this->signup->status);
        $this->assertNotNull($this->signup->assigned_at);
        $this->assertNull($this->signup->assigned_by);
    }

    public function test_an_area_manager_can_remove_a_signup_in_their_area()
    {
        $this->actingAs($this->manager)
            ->delete("/me/signups/{$this->signup->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('shift_signups', ['id' => $this->signup->id]);
    }

    public function test_a_manager_of_another_area_cannot_moderate()
    {
        $otherArea = Area::factory()->for($this->area->event)->create(['tenant_id' => $this->area->tenant_id]);
        $otherManager = Person::factory()->create(['tenant_id' => $this->area->tenant_id]);
        PersonRole::factory()->for($otherManager)->for($this->area->event)->create([
            'tenant_id' => $this->area->tenant_id,
            'role' => Role::AreaManager,
            'area_id' => $otherArea->id,
        ]);

        $this->actingAs($otherManager)
            ->put("/me/signups/{$this->signup->id}", ['status' => 'assigned'])
            ->assertNotFound();
    }

    public function test_a_plain_volunteer_cannot_moderate()
    {
        $volunteer = Person::factory()->create(['tenant_id' => $this->area->tenant_id]);

        $this->actingAs($volunteer)
            ->put("/me/signups/{$this->signup->id}", ['status' => 'assigned'])
            ->assertNotFound();
        $this->actingAs($volunteer)
            ->delete("/me/signups/{$this->signup->id}")
            ->assertNotFound();
    }
}
