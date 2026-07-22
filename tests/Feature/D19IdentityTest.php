<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SignupStatus;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Shift;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D19: one identity, roles orthogonal to it. Organizers and area managers
 * hold password accounts; simple volunteers use magic links; and anyone,
 * whatever their role, can sign up for shifts.
 */
class D19IdentityTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::factory()->create();
    }

    public function test_a_volunteer_cannot_reach_the_organizer_area()
    {
        $tenant = $this->tenant();
        $volunteer = Person::factory()->for($tenant)->create();

        $this->actingAs($volunteer)->get('/dashboard')->assertForbidden();
        $this->actingAs($volunteer)->get('/people')->assertForbidden();
        $this->actingAs($volunteer)->get('/events')->assertForbidden();
    }

    public function test_an_area_manager_is_not_an_organizer()
    {
        $tenant = $this->tenant();
        $event = Event::factory()->for($tenant)->create();
        $area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);

        $manager = Person::factory()->withPassword()->for($tenant)->create();
        PersonRole::factory()->for($manager)->for($event)->create([
            'tenant_id' => $tenant->id,
            'role' => Role::AreaManager,
            'area_id' => $area->id,
        ]);

        // A password login, but no organizer powers: the manage area is closed.
        $this->actingAs($manager)->get('/dashboard')->assertForbidden();
        $this->actingAs($manager)->get('/people')->assertForbidden();
    }

    public function test_a_signed_in_volunteer_hitting_a_guest_page_goes_to_their_shifts()
    {
        // Regression: a magic-link volunteer opening /register or /login used
        // to be bounced onto the organizer dashboard and hit a 403.
        $volunteer = Person::factory()->for($this->tenant())->create();

        $this->actingAs($volunteer)->get('/register')->assertRedirect(route('volunteer.home'));
        $this->actingAs($volunteer)->get('/login')->assertRedirect(route('volunteer.home'));
    }

    public function test_a_signed_in_organizer_hitting_a_guest_page_goes_to_the_dashboard()
    {
        $organizer = Person::factory()->organizer()->for($this->tenant())->create();

        $this->actingAs($organizer)->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_an_organizer_reaches_the_organizer_area()
    {
        $organizer = Person::factory()->organizer()->for($this->tenant())->create();

        $this->actingAs($organizer)->get('/dashboard')->assertOk();
        $this->actingAs($organizer)->get('/people')->assertOk();
    }

    public function test_anyone_can_sign_up_for_a_shift_regardless_of_role()
    {
        $tenant = $this->tenant();
        $event = Event::factory()->for($tenant)->create();
        $area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);
        $shift = Shift::factory()->for($area)->create([
            'tenant_id' => $tenant->id,
            'starts_at' => now()->addDays(3)->setTime(18, 0),
            'ends_at' => now()->addDays(3)->setTime(22, 0),
        ]);

        // An organizer — an account holder, not a plain volunteer — signs up.
        $organizer = Person::factory()->organizer()->for($tenant)->create();

        $this->actingAs($organizer)
            ->post("/me/shifts/{$shift->id}/signup")
            ->assertRedirect();

        $this->assertDatabaseHas('shift_signups', [
            'shift_id' => $shift->id,
            'person_id' => $organizer->id,
            'status' => SignupStatus::Available->value,
        ]);
    }
}
