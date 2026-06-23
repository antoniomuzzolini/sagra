<?php

namespace Tests\Feature\Manage;

use App\Enums\Role;
use App\Enums\SignupStatus;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagePeopleTest extends TestCase
{
    use RefreshDatabase;

    private function organizer(): User
    {
        return User::factory()->for(Tenant::factory())->create();
    }

    public function test_guests_cannot_see_the_people_page()
    {
        $this->get('/people')->assertRedirect(route('login'));
    }

    public function test_organizers_see_only_their_tenant_people()
    {
        $user = $this->organizer();
        Person::factory()->create(['tenant_id' => $user->tenant_id, 'name' => 'Mario Rossi']);
        Person::factory()->create(); // other tenant

        $this->actingAs($user)
            ->get('/people')
            ->assertOk()
            ->assertSee('Mario Rossi');

        $this->assertSame(1, Person::where('tenant_id', $user->tenant_id)->count());
    }

    public function test_the_roster_derives_role_areas_and_shift_count()
    {
        $user = $this->organizer();
        $tenant = $user->tenant_id;
        $event = Event::factory()->create(['tenant_id' => $tenant]);
        $kitchen = Area::factory()->for($event)->create(['tenant_id' => $tenant, 'name' => 'Cucina']);
        $bar = Area::factory()->for($event)->create(['tenant_id' => $tenant, 'name' => 'Bar']);

        // A plain volunteer assigned to a kitchen shift: area derived from the
        // signup, one shift counted.
        $aldo = Person::factory()->create(['tenant_id' => $tenant, 'name' => 'Aldo Bianchi']);
        $shift = Shift::factory()->for($kitchen)->create(['tenant_id' => $tenant]);
        ShiftSignup::factory()->for($shift)->for($aldo)->create(['tenant_id' => $tenant, 'status' => SignupStatus::Assigned]);

        // A bar manager with no signups: still belongs to the bar (managed),
        // role is manager, zero shifts.
        $bea = Person::factory()->create(['tenant_id' => $tenant, 'name' => 'Bea Costa']);
        PersonRole::factory()->for($bea)->for($event)->create([
            'tenant_id' => $tenant,
            'role' => Role::AreaManager,
            'area_id' => $bar->id,
        ]);

        $this->actingAs($user)->get('/people')->assertInertia(
            fn ($page) => $page
                ->component('People/Index')
                ->where('people.0.name', 'Aldo Bianchi')
                ->where('people.0.role', 'volunteer')
                ->where('people.0.areas', ['Cucina'])
                ->where('people.0.shiftsCount', 1)
                ->where('people.1.name', 'Bea Costa')
                ->where('people.1.role', 'manager')
                ->where('people.1.areas', ['Bar'])
                ->where('people.1.shiftsCount', 0)
        );
    }

    public function test_an_organizer_can_create_a_person()
    {
        $user = $this->organizer();

        $this->actingAs($user)
            ->post('/people', ['name' => 'Anna Verdi', 'phone' => '+393331112233', 'email' => ''])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('people', [
            'tenant_id' => $user->tenant_id,
            'name' => 'Anna Verdi',
        ]);
    }

    public function test_a_person_without_any_contact_is_rejected()
    {
        $this->actingAs($this->organizer())
            ->post('/people', ['name' => 'Anna Verdi', 'phone' => '', 'email' => ''])
            ->assertSessionHasErrors(['phone', 'email']);
    }

    public function test_an_organizer_can_update_and_delete_a_person()
    {
        $user = $this->organizer();
        $person = Person::factory()->create(['tenant_id' => $user->tenant_id]);

        $this->actingAs($user)
            ->put("/people/{$person->id}", ['name' => 'Nuovo Nome', 'phone' => $person->phone, 'email' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame('Nuovo Nome', $person->fresh()->name);

        $this->actingAs($user)->delete("/people/{$person->id}");

        $this->assertSoftDeleted($person);
    }

    public function test_the_magic_link_endpoint_flashes_the_url()
    {
        $user = $this->organizer();
        $person = Person::factory()->create(['tenant_id' => $user->tenant_id]);

        $response = $this->actingAs($user)->post("/people/{$person->id}/magic-link");

        $response->assertSessionHas('magicLink');
        $this->assertSame(1, $person->magicLinks()->count());
        $this->assertStringContainsString('/v/', session('magicLink')['url']);
    }

    public function test_cross_tenant_access_is_a_404()
    {
        $user = $this->organizer();
        $foreign = Person::factory()->create(); // other tenant

        $this->actingAs($user)
            ->put("/people/{$foreign->id}", ['name' => 'X', 'phone' => '123', 'email' => ''])
            ->assertNotFound();
        $this->actingAs($user)->delete("/people/{$foreign->id}")->assertNotFound();
        $this->actingAs($user)->post("/people/{$foreign->id}/magic-link")->assertNotFound();
    }
}
