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
