<?php

namespace Tests\Feature\Volunteer;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_volunteer_can_complete_the_registration_with_a_phone()
    {
        $person = Person::factory()->create(['phone' => null, 'email' => null]);

        $this->actingAs($person)
            ->put('/me/contact', ['phone' => '+39 333 1234567', 'email' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame('+39 333 1234567', $person->fresh()->phone);
    }

    public function test_at_least_one_contact_is_required()
    {
        $person = Person::factory()->create(['phone' => null, 'email' => null]);

        $this->actingAs($person)
            ->put('/me/contact', ['phone' => '', 'email' => ''])
            ->assertSessionHasErrors(['phone', 'email']);
    }

    public function test_a_contact_already_taken_in_the_tenant_is_rejected()
    {
        $person = Person::factory()->create(['phone' => null, 'email' => null]);
        Person::factory()->create(['tenant_id' => $person->tenant_id, 'phone' => '+39 333 1234567']);

        $this->actingAs($person)
            ->put('/me/contact', ['phone' => '+39 333 1234567', 'email' => ''])
            ->assertSessionHasErrors('phone');
    }

    public function test_the_home_nudges_only_people_without_contacts()
    {
        $without = Person::factory()->create(['phone' => null, 'email' => null]);
        $with = Person::factory()->create(['phone' => '+39 333 1234567']);

        $this->actingAs($without)->get('/me')
            ->assertInertia(fn ($page) => $page->where('person.needsContact', true));

        $this->actingAs($with)->get('/me')
            ->assertInertia(fn ($page) => $page->where('person.needsContact', false)->where('person.phone', '+39 333 1234567'));
    }

    public function test_existing_contacts_can_be_changed()
    {
        $person = Person::factory()->create(['phone' => '+39 333 1234567', 'email' => null]);

        $this->actingAs($person)
            ->put('/me/contact', ['phone' => '+39 333 7654321', 'email' => 'mario@example.com'])
            ->assertSessionHasNoErrors();

        $person->refresh();
        $this->assertSame('+39 333 7654321', $person->phone);
        $this->assertSame('mario@example.com', $person->email);
    }
}
