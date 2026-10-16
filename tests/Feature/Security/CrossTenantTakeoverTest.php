<?php

namespace Tests\Feature\Security;

use App\Models\Person;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Credentials are global, the registry is per tenant (D25). Several
 * associations may hold a person with the same email, so signing in,
 * resetting a password or issuing an account invite must never settle for
 * "the first person with that email": on PostgreSQL that is whichever row the
 * heap returns first, and it may belong to another association.
 *
 * @see docs/review/03-roadmap.md §1.1 and step S.2b
 */
class CrossTenantTakeoverTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'vittima@example.com';

    private const TAKEN = 'Questa email è già usata su Fieste.';

    /**
     * @return array<string, array{string}>
     */
    public static function namesakes(): array
    {
        return [
            'added by an organizer' => ['people'],
            'self-registered, then contact added' => ['join'],
            'already there before the victim' => ['earlier'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function profileEntries(): array
    {
        return [
            'settings profile' => ['settings'],
            'own contact' => ['contact'],
            'organizer edit' => ['people'],
        ];
    }

    public function test_account_invite_without_body_checks_the_stored_email()
    {
        $this->victim();
        [$attacker, $bait] = $this->bait('people');

        // No email in the body: the invite falls back to the stored one, and
        // the set-password token is keyed by that email alone.
        $this->actingAs($attacker)
            ->post("/people/{$bait->id}/account-invite")
            ->assertSessionHasErrors(['email' => self::TAKEN])
            ->assertSessionMissing('accountInvite');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => self::EMAIL]);
    }

    public function test_me_contact_then_account_invite_cannot_take_over_another_tenant()
    {
        $this->victim();
        [$attacker, $bait] = $this->bait('join');

        $this->actingAs($attacker)
            ->post("/people/{$bait->id}/account-invite")
            ->assertSessionHasErrors(['email' => self::TAKEN])
            ->assertSessionMissing('accountInvite');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => self::EMAIL]);
    }

    #[DataProvider('namesakes')]
    public function test_password_reset_never_reaches_another_tenants_person(string $via)
    {
        [$victim, $namesake] = $this->victimAndNamesake($via);

        // The victim asked for a reset and got the link by email.
        $token = Password::broker('people')->createToken($victim);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => self::EMAIL,
            'password' => 'nuova-password',
            'password_confirmation' => 'nuova-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('nuova-password', $victim->fresh()->password));
        $this->assertNull($namesake->fresh()->password);
    }

    #[DataProvider('namesakes')]
    public function test_a_passwordless_namesake_elsewhere_never_blocks_login(string $via)
    {
        [$victim] = $this->victimAndNamesake($via);

        $this->post('/login', ['email' => self::EMAIL, 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($victim);
    }

    #[DataProvider('namesakes')]
    public function test_forgot_password_reaches_the_row_with_a_password(string $via)
    {
        Notification::fake();
        [$victim, $namesake] = $this->victimAndNamesake($via);

        $this->post('/forgot-password', ['email' => self::EMAIL])->assertSessionHasNoErrors();

        Notification::assertSentTo($victim, ResetPassword::class);
        Notification::assertNotSentTo($namesake, ResetPassword::class);
    }

    #[DataProvider('profileEntries')]
    public function test_a_password_holder_cannot_move_to_an_email_used_elsewhere(string $entry)
    {
        $this->victim();
        $person = Person::factory()->withPassword()->create();

        $this->submitProfile($entry, $person, self::EMAIL)
            ->assertSessionHasErrors(['email' => self::TAKEN]);

        $this->assertNotSame(self::EMAIL, $person->fresh()->email);
    }

    public function test_a_pending_invite_cannot_reach_an_account_that_moves_to_its_email()
    {
        // The email is nowhere else yet, so the attacker's invite goes through.
        [$attacker, $bait] = $this->bait('people');
        $this->actingAs($attacker)
            ->post("/people/{$bait->id}/account-invite")
            ->assertSessionHasNoErrors();
        $url = session('accountInvite')['url'];

        // Then a password holder of another association moves to that email:
        // the pending token would reset their password instead of the bait's.
        $victim = Person::factory()->organizer()->create();
        $this->actingAs($victim)
            ->patch('/settings/profile', ['name' => $victim->name, 'email' => self::EMAIL])
            ->assertSessionHasErrors(['email' => self::TAKEN]);

        $this->app['auth']->forgetGuards();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->post('/reset-password', [
            'token' => basename((string) parse_url($url, PHP_URL_PATH)),
            'email' => $query['email'],
            'password' => 'nuova-password',
            'password_confirmation' => 'nuova-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('nuova-password', $bait->fresh()->password));
        $this->assertTrue(Hash::check('password', $victim->fresh()->password));
    }

    #[DataProvider('profileEntries')]
    public function test_editing_with_unchanged_email_works_when_a_namesake_exists_elsewhere(string $entry)
    {
        $person = Person::factory()->withPassword()->create(['email' => self::EMAIL]);
        // Namesakes elsewhere: one added afterwards without a password, one
        // holding a password since before credentials were global (step 6
        // reports these). Only a change of email is checked, so the person
        // can still edit everything else.
        Person::factory()->create(['phone' => null, 'email' => self::EMAIL]);
        Person::factory()->withPassword()->create(['email' => self::EMAIL]);

        $this->submitProfile($entry, $person, self::EMAIL)->assertSessionHasNoErrors();

        $this->assertSame(self::EMAIL, $person->fresh()->email);
    }

    public function test_invite_is_refused_when_a_passwordless_namesake_exists_elsewhere()
    {
        // Known limit of D25: the invite token is keyed by the email, so a
        // person whose email sits in another association, even without a
        // password, can't get an account until accounts are global (ADR-004 C).
        Person::factory()->create(['phone' => null, 'email' => self::EMAIL]);
        $organizer = Person::factory()->organizer()->create();
        $manager = Person::factory()->create(['tenant_id' => $organizer->tenant_id]);

        $this->actingAs($organizer)
            ->post("/people/{$manager->id}/account-invite", ['email' => self::EMAIL])
            ->assertSessionHasErrors(['email' => self::TAKEN])
            ->assertSessionMissing('accountInvite');

        $this->assertNull($manager->fresh()->email);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => self::EMAIL]);
    }

    /**
     * An organizer of another association, holding a password like the victim.
     */
    private function victim(): Person
    {
        return Person::factory()->organizer()->create(['email' => self::EMAIL]);
    }

    /**
     * The attacker's own tenant gets a person with the victim's email, through
     * an entry that only checks uniqueness within the tenant: the organizer
     * adds it, or a volunteer self-registers and then adds the email.
     *
     * @return array{Person, Person} the attacker (an organizer) and the bait
     */
    private function bait(string $via): array
    {
        $attacker = Person::factory()->organizer()->create();

        if ($via === 'people') {
            $this->actingAs($attacker)
                ->post('/people', ['name' => 'Esca', 'email' => self::EMAIL])
                ->assertSessionHasNoErrors();
        } else {
            $this->post('/join/'.$attacker->tenant->inviteToken(), ['name' => 'Esca'])
                ->assertRedirect(route('volunteer.home'));
            $this->put('/me/contact', ['phone' => '', 'email' => self::EMAIL])
                ->assertSessionHasNoErrors();
            $this->post('/logout');
        }

        $this->app['auth']->forgetGuards();

        $bait = Person::query()
            ->where('tenant_id', $attacker->tenant_id)
            ->where('email', self::EMAIL)
            ->sole();

        return [$attacker, $bait];
    }

    /**
     * The victim holds a password; another tenant holds a passwordless person
     * with the same email. With 'earlier' the namesake was already there, so
     * it has the lower id and every engine scans it first.
     *
     * @return array{Person, Person} the victim and the namesake
     */
    private function victimAndNamesake(string $via): array
    {
        if ($via === 'earlier') {
            $namesake = Person::factory()->create(['phone' => null, 'email' => self::EMAIL]);

            return [$this->victim(), $namesake];
        }

        $victim = $this->victim();
        [, $namesake] = $this->bait($via);

        // On PostgreSQL an UPDATE writes a new tuple at the end of the heap,
        // so a query without ORDER BY now finds the namesake first. SQLite
        // keeps returning rows by id: there this changes nothing. (Not
        // touch(): within the same second it isn't dirty and writes nothing.)
        Person::query()->whereKey($victim->id)->update(['name' => $victim->name]);

        return [$victim, $namesake];
    }

    /**
     * Save $email on $person through one of the entries that edit an
     * existing person's email.
     */
    private function submitProfile(string $entry, Person $person, string $email): TestResponse
    {
        return match ($entry) {
            'settings' => $this->actingAs($person)
                ->patch('/settings/profile', ['name' => 'Nome Nuovo', 'email' => $email]),
            'contact' => $this->actingAs($person)
                ->put('/me/contact', ['phone' => '+39 333 1234567', 'email' => $email]),
            'people' => $this->actingAs(Person::factory()->organizer()->create(['tenant_id' => $person->tenant_id]))
                ->put("/people/{$person->id}", ['name' => 'Nome Nuovo', 'phone' => '', 'email' => $email]),
        };
    }
}
