<?php

namespace Tests\Feature\Core;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_person_can_exist_without_contacts()
    {
        // Self-registered volunteers (D16) may leave every contact blank;
        // organizer-created people still need one (controller validation).
        $person = Person::factory()->create(['phone' => null, 'email' => null]);

        $this->assertTrue($person->exists);
    }

    public function test_a_single_contact_is_enough()
    {
        $byPhone = Person::factory()->create(['phone' => '+393331234567', 'email' => null]);
        $byEmail = Person::factory()->create(['phone' => null, 'email' => 'maria@example.com']);

        $this->assertTrue($byPhone->exists);
        $this->assertTrue($byEmail->exists);
    }
}
