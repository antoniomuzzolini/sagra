<?php

namespace Tests\Feature\Volunteer;

use App\Models\Person;
use App\Models\Tenant;
use App\Notifications\MagicLinkRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_person_with_an_email_gets_a_fresh_link_automatically()
    {
        Notification::fake();
        $person = Person::factory()->create(['email' => 'maria@example.com']);

        $this->post('/recover', ['contact' => 'maria@example.com'])
            ->assertRedirect()
            ->assertSessionHas('recoveryRequested', true);

        Notification::assertSentTo($person, MagicLinkRecovery::class);
        $this->assertSame(1, $person->magicLinks()->whereNull('last_used_at')->count());
    }

    public function test_a_phone_only_person_flags_the_request_for_the_organizer()
    {
        Notification::fake();
        $person = Person::factory()->create(['phone' => '+39 333 1234567', 'email' => null]);

        // Different formatting, same number.
        $this->post('/recover', ['contact' => '3331234567'])->assertRedirect();

        Notification::assertNothingSent();
        $this->assertNotNull($person->fresh()->link_requested_at);
    }

    public function test_an_unknown_contact_gets_the_same_neutral_answer()
    {
        Notification::fake();

        $this->post('/recover', ['contact' => 'nessuno@example.com'])
            ->assertRedirect()
            ->assertSessionHas('recoveryRequested', true);

        Notification::assertNothingSent();
    }

    public function test_the_same_email_in_two_tenants_recovers_both_people()
    {
        Notification::fake();
        $one = Person::factory()->create(['email' => 'maria@example.com']);
        $two = Person::factory()->create(['email' => 'maria@example.com']);

        $this->post('/recover', ['contact' => 'maria@example.com']);

        Notification::assertSentTo($one, MagicLinkRecovery::class);
        Notification::assertSentTo($two, MagicLinkRecovery::class);
    }

    public function test_generating_a_new_link_clears_the_pending_request()
    {
        $user = Person::factory()->organizer()->for(Tenant::factory())->create();
        $person = Person::factory()->create([
            'tenant_id' => $user->tenant_id,
            'link_requested_at' => now(),
        ]);

        $this->actingAs($user)->post("/people/{$person->id}/magic-link");

        $this->assertNull($person->fresh()->link_requested_at);
    }
}
