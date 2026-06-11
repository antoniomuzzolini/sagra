<?php

namespace Tests\Feature\Core;

use App\Models\Person;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_person_needs_at_least_one_contact()
    {
        $this->expectException(DomainException::class);

        Person::factory()->create(['phone' => null, 'email' => null]);
    }

    public function test_a_single_contact_is_enough()
    {
        $byPhone = Person::factory()->create(['phone' => '+393331234567', 'email' => null]);
        $byEmail = Person::factory()->create(['phone' => null, 'email' => 'maria@example.com']);

        $this->assertTrue($byPhone->exists);
        $this->assertTrue($byEmail->exists);
    }
}
