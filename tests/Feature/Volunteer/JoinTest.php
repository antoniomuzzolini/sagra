<?php

namespace Tests\Feature\Volunteer;

use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JoinTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_invite_link_shows_the_join_form()
    {
        $tenant = Tenant::factory()->create(['name' => 'Pro Loco']);

        $this->get('/join/'.$tenant->inviteToken())->assertInertia(
            fn ($page) => $page
                ->component('Volunteer/Join')
                ->where('tenantName', 'Pro Loco')
                ->where('currentName', null)
        );
    }

    public function test_a_volunteer_can_self_register_with_just_a_name()
    {
        $tenant = Tenant::factory()->create();

        $this->post('/join/'.$tenant->inviteToken(), ['name' => 'Maria Rossi'])
            ->assertRedirect(route('volunteer.home'));

        $person = Person::where('tenant_id', $tenant->id)->first();
        $this->assertSame('Maria Rossi', $person->name);
        $this->assertNull($person->phone);
        $this->assertAuthenticatedAs($person);
    }

    public function test_the_phone_is_optional_but_unique_within_the_tenant()
    {
        $tenant = Tenant::factory()->create();
        Person::factory()->create(['tenant_id' => $tenant->id, 'phone' => '+393331234567']);

        $this->post('/join/'.$tenant->inviteToken(), [
            'name' => 'Maria Rossi',
            'phone' => '+393331234567',
        ])->assertSessionHasErrors('phone');

        $this->assertSame(1, Person::where('tenant_id', $tenant->id)->count());
    }

    public function test_an_unknown_invite_token_is_rejected()
    {
        $this->get('/join/not-a-token')->assertNotFound();
        $this->post('/join/not-a-token', ['name' => 'Maria'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_a_device_already_in_use_sees_who_it_belongs_to()
    {
        $tenant = Tenant::factory()->create();
        $existing = Person::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Mario Bianchi']);

        $this->actingAs($existing)
            ->get('/join/'.$tenant->inviteToken())
            ->assertInertia(fn ($page) => $page->where('currentName', 'Mario Bianchi'));
    }

    public function test_registering_while_logged_in_hands_the_device_to_the_new_person()
    {
        $tenant = Tenant::factory()->create();
        $existing = Person::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($existing)
            ->post('/join/'.$tenant->inviteToken(), ['name' => 'Luigia Verdi'])
            ->assertRedirect(route('volunteer.home'));

        $new = Person::where('name', 'Luigia Verdi')->first();
        $this->assertAuthenticatedAs($new);
    }

    public function test_regenerating_the_invite_revokes_the_old_link()
    {
        $user = Person::factory()->organizer()->for(Tenant::factory())->create();
        $oldToken = $user->tenant->inviteToken();

        $this->actingAs($user)->post('/invite/regenerate')->assertRedirect();

        $this->get("/join/{$oldToken}")->assertNotFound();
        $this->get('/join/'.$user->tenant->fresh()->invite_token)->assertOk();
    }

    public function test_the_people_page_exposes_the_invite_url()
    {
        $user = Person::factory()->organizer()->for(Tenant::factory())->create();

        $this->actingAs($user)->get('/people')->assertInertia(
            fn ($page) => $page->where('inviteUrl', fn ($url) => str_contains($url, '/join/'))
        );
    }
}
