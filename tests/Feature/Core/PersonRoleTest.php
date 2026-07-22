<?php

namespace Tests\Feature\Core;

use App\Enums\Role;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_area_manager_role_must_be_scoped_to_an_area()
    {
        $this->expectException(DomainException::class);

        PersonRole::factory()->create(['role' => Role::AreaManager, 'area_id' => null]);
    }

    public function test_an_area_manager_role_with_an_area_is_valid()
    {
        $event = Event::factory()->create();
        $area = Area::factory()->for($event)->create();
        $person = Person::factory()->create(['tenant_id' => $event->tenant_id]);

        $role = PersonRole::factory()->for($person)->for($event)->create([
            'role' => Role::AreaManager,
            'area_id' => $area->id,
        ]);

        $this->assertTrue($role->exists);
    }
}
