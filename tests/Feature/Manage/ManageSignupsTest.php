<?php

namespace Tests\Feature\Manage;

use App\Enums\SignupStatus;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageSignupsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ShiftSignup $signup;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($tenant)->create();
        $event = Event::factory()->create(['tenant_id' => $tenant->id]);
        $area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);
        $shift = Shift::factory()->for($area)->create(['tenant_id' => $tenant->id]);
        $person = Person::factory()->create(['tenant_id' => $tenant->id]);
        $this->signup = ShiftSignup::factory()->for($shift)->for($person)->create();
    }

    public function test_an_organizer_can_confirm_an_availability()
    {
        $this->actingAs($this->user)
            ->put("/signups/{$this->signup->id}", ['status' => 'assigned'])
            ->assertRedirect();

        $this->signup->refresh();
        $this->assertSame(SignupStatus::Assigned, $this->signup->status);
        $this->assertNotNull($this->signup->assigned_at);
        $this->assertSame($this->user->id, $this->signup->assigned_by);
    }

    public function test_an_organizer_can_decline_an_availability()
    {
        $this->actingAs($this->user)->put("/signups/{$this->signup->id}", ['status' => 'declined']);

        $this->assertSame(SignupStatus::Declined, $this->signup->fresh()->status);
    }

    public function test_unassigning_clears_the_assignment_metadata()
    {
        $this->signup->update([
            'status' => SignupStatus::Assigned,
            'assigned_at' => now(),
            'assigned_by' => $this->user->id,
            'substitution_requested_at' => now(),
        ]);

        $this->actingAs($this->user)->put("/signups/{$this->signup->id}", ['status' => 'available']);

        $this->signup->refresh();
        $this->assertSame(SignupStatus::Available, $this->signup->status);
        $this->assertNull($this->signup->assigned_at);
        $this->assertNull($this->signup->assigned_by);
        $this->assertNull($this->signup->substitution_requested_at);
    }

    public function test_an_organizer_can_remove_a_signup()
    {
        $this->actingAs($this->user)->delete("/signups/{$this->signup->id}");

        $this->assertDatabaseMissing('shift_signups', ['id' => $this->signup->id]);
    }

    public function test_cross_tenant_signups_are_invisible()
    {
        $foreign = User::factory()->for(Tenant::factory())->create();

        $this->actingAs($foreign)
            ->put("/signups/{$this->signup->id}", ['status' => 'assigned'])
            ->assertNotFound();
        $this->actingAs($foreign)->delete("/signups/{$this->signup->id}")->assertNotFound();
    }
}
