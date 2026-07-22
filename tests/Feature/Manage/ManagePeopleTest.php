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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ManagePeopleTest extends TestCase
{
    use RefreshDatabase;

    private function organizer(): Person
    {
        return Person::factory()->organizer()->for(Tenant::factory())->create();
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

        $this->assertSame(1, Person::where('tenant_id', $user->tenant_id)
            ->where('is_organizer', false)->count());
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
        $this->actingAs($user)->post("/people/{$foreign->id}/account-invite")->assertNotFound();
    }

    public function test_inviting_a_person_as_an_account_flashes_a_set_password_link()
    {
        $user = $this->organizer();
        $person = Person::factory()->create([
            'tenant_id' => $user->tenant_id,
            'phone' => null,
            'email' => 'bea@example.com',
        ]);

        $response = $this->actingAs($user)->post("/people/{$person->id}/account-invite");

        $response->assertSessionHasNoErrors()->assertSessionHas('accountInvite');
        $this->assertStringContainsString('reset-password', session('accountInvite')['url']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'bea@example.com']);
    }

    public function test_a_contactless_person_needs_an_email_to_get_an_account()
    {
        $user = $this->organizer();
        $person = Person::factory()->create(['tenant_id' => $user->tenant_id, 'phone' => '+39333', 'email' => null]);

        $this->actingAs($user)->post("/people/{$person->id}/account-invite")
            ->assertSessionHasErrors('email');

        // Supplying the email in the same step both saves it and issues the link.
        $this->actingAs($user)->post("/people/{$person->id}/account-invite", ['email' => 'new@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('accountInvite');

        $this->assertSame('new@example.com', $person->fresh()->email);
    }

    public function test_the_invited_person_can_set_a_password_and_log_in()
    {
        $user = $this->organizer();
        $person = Person::factory()->create([
            'tenant_id' => $user->tenant_id,
            'phone' => null,
            'email' => 'resp@example.com',
        ]);

        // The token is what the invite's set-password link carries (the invite
        // endpoint itself is covered above).
        $token = Password::broker('people')->createToken($person);

        // The invited person opens the link as a guest and picks a password.
        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'resp@example.com',
            'password' => 'secretpassword',
            'password_confirmation' => 'secretpassword',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        // From then on they log in with email + password (D19); without the
        // organizer role they land on their own shifts, not the dashboard.
        $this->post('/login', ['email' => 'resp@example.com', 'password' => 'secretpassword'])
            ->assertRedirect(route('volunteer.home'));
        $this->assertAuthenticatedAs($person->fresh());
    }

    public function test_email_login_is_case_insensitive()
    {
        $user = $this->organizer();
        $person = Person::factory()->create(['tenant_id' => $user->tenant_id, 'phone' => '+39333', 'email' => null]);

        // The organizer types the email with capitals; it is stored lower-case.
        $this->actingAs($user)->post("/people/{$person->id}/account-invite", ['email' => 'Bea@Example.com'])
            ->assertSessionHasNoErrors();
        $this->assertSame('bea@example.com', $person->fresh()->email);

        // The invited person acts in a fresh guest session.
        $this->app['auth']->forgetGuards();

        $token = Password::broker('people')->createToken($person->fresh());
        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'bea@example.com',
            'password' => 'secretpassword',
            'password_confirmation' => 'secretpassword',
        ])->assertSessionHasNoErrors();

        // The person logs in with the natural lower-case form and gets in.
        $this->post('/login', ['email' => 'BEA@example.com', 'password' => 'secretpassword'])
            ->assertRedirect(route('volunteer.home'));
        $this->assertAuthenticatedAs($person->fresh());
    }
}
