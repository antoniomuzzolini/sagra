<?php

namespace Tests\Feature\Manage;

use App\Enums\Role;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageAreaManagersTest extends TestCase
{
    use RefreshDatabase;

    private Person $user;

    private Area $area;

    private Person $person;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $this->user = Person::factory()->organizer()->for($tenant)->create();
        $event = Event::factory()->create(['tenant_id' => $tenant->id]);
        $this->area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);
        $this->person = Person::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_an_organizer_can_appoint_an_area_manager()
    {
        $this->actingAs($this->user)
            ->post("/areas/{$this->area->id}/managers", ['person_id' => $this->person->id])
            ->assertRedirect();

        $this->assertDatabaseHas('person_roles', [
            'person_id' => $this->person->id,
            'area_id' => $this->area->id,
            'event_id' => $this->area->event_id,
            'role' => 'area_manager',
        ]);
    }

    public function test_an_organizer_can_appoint_a_brand_new_person()
    {
        $this->actingAs($this->user)
            ->post("/areas/{$this->area->id}/managers", ['name' => 'Nuova Responsabile'])
            ->assertRedirect();

        $person = Person::where('tenant_id', $this->area->tenant_id)->where('name', 'Nuova Responsabile')->first();
        $this->assertNotNull($person);
        $this->assertDatabaseHas('person_roles', [
            'person_id' => $person->id,
            'area_id' => $this->area->id,
            'role' => 'area_manager',
        ]);
    }

    public function test_appointing_twice_creates_a_single_role()
    {
        $this->actingAs($this->user)->post("/areas/{$this->area->id}/managers", ['person_id' => $this->person->id]);
        $this->actingAs($this->user)->post("/areas/{$this->area->id}/managers", ['person_id' => $this->person->id]);

        $this->assertSame(1, PersonRole::count());
    }

    public function test_a_person_from_another_tenant_cannot_be_appointed()
    {
        $foreignPerson = Person::factory()->create();

        $this->actingAs($this->user)
            ->post("/areas/{$this->area->id}/managers", ['person_id' => $foreignPerson->id])
            ->assertNotFound();

        $this->assertSame(0, PersonRole::count());
    }

    public function test_an_organizer_can_remove_an_area_manager()
    {
        $role = PersonRole::factory()->for($this->person)->create([
            'tenant_id' => $this->area->tenant_id,
            'event_id' => $this->area->event_id,
            'role' => Role::AreaManager,
            'area_id' => $this->area->id,
        ]);

        $this->actingAs($this->user)->delete("/person-roles/{$role->id}")->assertRedirect();

        $this->assertDatabaseMissing('person_roles', ['id' => $role->id]);
    }

    public function test_cross_tenant_roles_cannot_be_removed()
    {
        $foreignUser = Person::factory()->organizer()->for(Tenant::factory())->create();
        $role = PersonRole::factory()->for($this->person)->create([
            'tenant_id' => $this->area->tenant_id,
            'event_id' => $this->area->event_id,
            'role' => Role::AreaManager,
            'area_id' => $this->area->id,
        ]);

        $this->actingAs($foreignUser)->delete("/person-roles/{$role->id}")->assertNotFound();
    }
}
