<?php

namespace Tests\Feature\Volunteer;

use App\Enums\Role;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErasureTest extends TestCase
{
    use RefreshDatabase;

    private function shiftAt(Tenant $tenant, string $when): Shift
    {
        $event = Event::factory()->for($tenant)->create();
        $area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);

        return Shift::factory()->for($area)->create(['tenant_id' => $tenant->id, 'starts_at' => $when]);
    }

    public function test_erasing_scrubs_the_personal_data_and_keeps_past_history()
    {
        $tenant = Tenant::factory()->create();
        $person = Person::factory()->for($tenant)->create([
            'name' => 'Mario Rossi',
            'phone' => '+39 333 1112222',
            'email' => 'mario@example.com',
            'notification_preferences' => ['seat_freed' => false],
        ]);
        $person->updatePushSubscription('https://push.test/endpoint', 'key', 'auth');
        $person->createMagicLink();

        $pastShift = $this->shiftAt($tenant, now()->subDays(5)->toDateTimeString());
        $futureShift = $this->shiftAt($tenant, now()->addDays(5)->toDateTimeString());
        $past = ShiftSignup::factory()->assigned()->for($pastShift)->for($person)->create(['tenant_id' => $tenant->id]);
        $future = ShiftSignup::factory()->assigned()->for($futureShift)->for($person)->create(['tenant_id' => $tenant->id]);

        $this->actingAs($person)->delete('/me/erase')->assertRedirect('/');

        $person->refresh();
        $this->assertSame('Utente rimosso', $person->name);
        $this->assertNull($person->phone);
        $this->assertNull($person->email);
        $this->assertNull($person->notification_preferences);
        $this->assertNotNull($person->deleted_at); // soft-deleted, out of active lists
        $this->assertSame(0, $person->magicLinks()->count());
        $this->assertSame(0, $person->pushSubscriptions()->count());

        // Past signup stays as anonymized history; the future one is cancelled.
        $this->assertNotNull($past->fresh());
        $this->assertNull($future->fresh());
    }

    public function test_erasing_removes_area_manager_roles()
    {
        $tenant = Tenant::factory()->create();
        $event = Event::factory()->for($tenant)->create();
        $area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);
        $manager = Person::factory()->for($tenant)->create(['email' => 'resp@example.com']);
        PersonRole::factory()->create([
            'tenant_id' => $tenant->id,
            'person_id' => $manager->id,
            'event_id' => $event->id,
            'area_id' => $area->id,
            'role' => Role::AreaManager,
        ]);

        $this->actingAs($manager)->delete('/me/erase')->assertRedirect('/');

        $this->assertSame(0, PersonRole::where('person_id', $manager->id)->count());
    }

    public function test_the_sole_organizer_cannot_erase_themselves()
    {
        $organizer = Person::factory()->organizer()->for(Tenant::factory())->create(['email' => 'org@example.com']);

        $this->actingAs($organizer)->delete('/me/erase')->assertSessionHasErrors('erase');

        $organizer->refresh();
        $this->assertSame('org@example.com', $organizer->email); // untouched
        $this->assertNull($organizer->deleted_at);
    }

    public function test_an_organizer_can_erase_when_another_one_remains()
    {
        $tenant = Tenant::factory()->create();
        $organizer = Person::factory()->organizer()->for($tenant)->create(['email' => 'org1@example.com']);
        Person::factory()->organizer()->for($tenant)->create(['email' => 'org2@example.com']);

        $this->actingAs($organizer)->delete('/me/erase')->assertRedirect('/');

        $organizer->refresh();
        $this->assertNull($organizer->email);
        $this->assertFalse($organizer->is_organizer);
    }

    public function test_deleting_the_account_from_settings_anonymizes_instead_of_keeping_pii()
    {
        $tenant = Tenant::factory()->create();
        // Not the last organizer, so erasure is allowed.
        $person = Person::factory()->organizer()->for($tenant)->create(['email' => 'a@example.com', 'password' => bcrypt('secret-pass')]);
        Person::factory()->organizer()->for($tenant)->create(['email' => 'b@example.com']);

        $this->actingAs($person)->delete('/settings/profile', ['password' => 'secret-pass'])->assertRedirect('/');

        $person->refresh();
        $this->assertSame('Utente rimosso', $person->name);
        $this->assertNull($person->email);
        $this->assertNotNull($person->deleted_at);
    }
}
