<?php

namespace Tests\Feature\Volunteer;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_magic_link_logs_the_person_in()
    {
        $person = Person::factory()->create();
        $token = $person->createMagicLink();

        $response = $this->get("/v/{$token}");

        $response->assertRedirect(route('volunteer.home'));
        $this->assertAuthenticatedAs($person, 'volunteer');
        $this->assertNotNull($person->magicLinks()->first()->last_used_at);
    }

    public function test_the_volunteer_home_is_reachable_after_login()
    {
        $person = Person::factory()->create();
        $token = $person->createMagicLink();

        $this->get("/v/{$token}");

        $this->get(route('volunteer.home'))->assertOk();
    }

    public function test_an_unknown_token_shows_a_friendly_error()
    {
        $this->get('/v/not-a-real-token')->assertNotFound();
        $this->assertGuest('volunteer');
    }

    public function test_an_expired_link_no_longer_works()
    {
        $person = Person::factory()->create();
        $token = $person->createMagicLink(now()->subDay());

        $this->get("/v/{$token}")->assertNotFound();
        $this->assertGuest('volunteer');
    }

    public function test_regenerating_the_link_revokes_the_previous_one()
    {
        $person = Person::factory()->create();
        $oldToken = $person->createMagicLink();
        $newToken = $person->createMagicLink();

        $this->get("/v/{$oldToken}")->assertNotFound();
        $this->assertGuest('volunteer');

        $this->get("/v/{$newToken}")->assertRedirect(route('volunteer.home'));
        $this->assertAuthenticatedAs($person, 'volunteer');
    }

    public function test_a_magic_link_works_only_once()
    {
        $person = Person::factory()->create();
        $token = $person->createMagicLink();

        $this->get("/v/{$token}")->assertRedirect(route('volunteer.home'));

        // Another device opens the same link: it is already consumed.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get("/v/{$token}")->assertNotFound();
        $this->assertGuest('volunteer');
    }

    public function test_a_used_link_reopened_on_the_same_device_goes_home()
    {
        $person = Person::factory()->create();
        $token = $person->createMagicLink();

        $this->get("/v/{$token}");
        $this->get("/v/{$token}")->assertRedirect(route('volunteer.home'));
        $this->assertAuthenticatedAs($person, 'volunteer');
    }

    public function test_links_expire_after_seven_days_by_default()
    {
        $person = Person::factory()->create();
        $token = $person->createMagicLink();

        $this->travel(8)->days();

        $this->get("/v/{$token}")->assertNotFound();
        $this->assertGuest('volunteer');
    }

    public function test_opening_anothers_link_asks_before_switching()
    {
        $mario = Person::factory()->create(['name' => 'Mario']);
        $luigia = Person::factory()->create(['tenant_id' => $mario->tenant_id, 'name' => 'Luigia']);
        $luigiaToken = $luigia->createMagicLink();

        $this->get('/v/'.$mario->createMagicLink());

        // The device belongs to Mario: Luigia's link asks, consumes nothing.
        $this->get("/v/{$luigiaToken}")->assertInertia(
            fn ($page) => $page
                ->component('Volunteer/SwitchIdentity')
                ->where('currentName', 'Mario')
                ->where('newName', 'Luigia')
        );
        $this->assertAuthenticatedAs($mario, 'volunteer');
        $this->assertNull($luigia->magicLinks()->first()->last_used_at);

        // Explicit confirmation hands the device over.
        $this->post("/v/{$luigiaToken}/switch")->assertRedirect(route('volunteer.home'));
        $this->assertAuthenticatedAs($luigia, 'volunteer');
        $this->assertNotNull($luigia->magicLinks()->first()->last_used_at);
    }

    public function test_regenerating_the_link_revokes_remember_sessions_too()
    {
        $person = Person::factory()->create();
        $person->createMagicLink();
        $before = $person->fresh()->remember_token;

        $person->createMagicLink();

        $this->assertNotSame($before, $person->fresh()->remember_token);
    }

    public function test_guests_are_redirected_to_the_invalid_link_page()
    {
        $this->get(route('volunteer.home'))
            ->assertRedirect(route('magic-link.invalid'));
    }

    public function test_a_magic_link_does_not_authenticate_the_web_guard()
    {
        $person = Person::factory()->create();
        $token = $person->createMagicLink();

        $this->get("/v/{$token}");

        $this->assertAuthenticatedAs($person, 'volunteer');
        $this->assertGuest('web');
    }
}
