<?php

namespace Tests\Feature\Core;

use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_counts_only_assigned_signups()
    {
        $shift = Shift::factory()->create(['needed_people' => 3]);
        $people = Person::factory(3)->create(['tenant_id' => $shift->tenant_id]);

        ShiftSignup::factory()->assigned()->for($shift)->for($people[0])->create();
        ShiftSignup::factory()->assigned()->for($shift)->for($people[1])->create();
        ShiftSignup::factory()->for($shift)->for($people[2])->create(); // still only available

        $this->assertSame(2, $shift->assignedCount());
        $this->assertFalse($shift->isFullyStaffed());
    }

    public function test_a_shift_is_fully_staffed_when_assigned_meets_the_need()
    {
        $shift = Shift::factory()->create(['needed_people' => 1]);
        $person = Person::factory()->create(['tenant_id' => $shift->tenant_id]);

        ShiftSignup::factory()->assigned()->for($shift)->for($person)->create();

        $this->assertTrue($shift->isFullyStaffed());
    }

    public function test_a_person_cannot_sign_up_twice_for_the_same_shift()
    {
        $shift = Shift::factory()->create();
        $person = Person::factory()->create(['tenant_id' => $shift->tenant_id]);

        ShiftSignup::factory()->for($shift)->for($person)->create();

        $this->expectException(QueryException::class);

        ShiftSignup::factory()->for($shift)->for($person)->create();
    }
}
