<?php

namespace Tests\Feature\Manage;

use App\Enums\Role;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\SubArea;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FornitureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Person $organizer;

    private Event $event;

    private Area $cucina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->organizer = Person::factory()->organizer()->for($this->tenant)->create();
        $this->event = Event::factory()->for($this->tenant)->create();
        $this->cucina = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'name' => 'Cucina']);
    }

    private function asOrganizer()
    {
        return $this->actingAs($this->organizer)->withSession(['current_event_id' => $this->event->id]);
    }

    public function test_an_organizer_adds_a_supplier()
    {
        $this->actingAs($this->organizer)->post('/suppliers', ['name' => 'Molino Rossi', 'phone' => '333', 'email' => 'm@rossi.it'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('suppliers', ['tenant_id' => $this->tenant->id, 'name' => 'Molino Rossi']);
    }

    public function test_an_organizer_records_a_supply()
    {
        $sub = SubArea::factory()->for($this->cucina)->create(['tenant_id' => $this->tenant->id]);
        $supplier = Supplier::factory()->for($this->tenant)->create();

        $this->asOrganizer()->post('/supplies', [
            'type' => 'rental',
            'description' => 'Gazebo 3x3',
            'area_id' => $this->cucina->id,
            'sub_area_id' => $sub->id,
            'supplier_id' => $supplier->id,
            'cost' => 120.5,
            'acquired_on' => '2026-07-01',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $supply = Supply::firstOrFail();
        $this->assertSame($this->event->id, $supply->event_id);
        $this->assertSame('Gazebo 3x3', $supply->description);
        $this->assertSame($sub->id, $supply->sub_area_id);
        $this->assertSame('120.50', $supply->cost);
    }

    public function test_a_supply_rejects_an_area_of_another_event()
    {
        $otherArea = Area::factory()->for(Event::factory()->for($this->tenant))->create(['tenant_id' => $this->tenant->id]);

        $this->asOrganizer()->post('/supplies', [
            'type' => 'purchase',
            'description' => 'Farina',
            'area_id' => $otherArea->id,
        ])->assertSessionHasErrors('area_id');
    }

    public function test_a_manager_records_supplies_only_for_their_areas()
    {
        $manager = Person::factory()->for($this->tenant)->create();
        PersonRole::factory()->create([
            'tenant_id' => $this->tenant->id, 'person_id' => $manager->id,
            'event_id' => $this->event->id, 'area_id' => $this->cucina->id, 'role' => Role::AreaManager,
        ]);
        $bar = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'name' => 'Bar']);

        $session = ['current_event_id' => $this->event->id];

        // Own area: allowed.
        $this->actingAs($manager)->withSession($session)->post('/supplies', [
            'type' => 'purchase', 'description' => 'Farina', 'area_id' => $this->cucina->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Another area: blocked.
        $this->actingAs($manager)->withSession($session)->post('/supplies', [
            'type' => 'purchase', 'description' => 'Bicchieri', 'area_id' => $bar->id,
        ])->assertNotFound();

        // Event-level (no area): organizer's, not the manager's.
        $this->actingAs($manager)->withSession($session)->post('/supplies', [
            'type' => 'rental', 'description' => 'Palco', 'area_id' => null,
        ])->assertNotFound();

        $this->assertSame(1, Supply::count());
    }

    public function test_the_index_scopes_supplies_by_role()
    {
        $manager = Person::factory()->for($this->tenant)->create();
        PersonRole::factory()->create([
            'tenant_id' => $this->tenant->id, 'person_id' => $manager->id,
            'event_id' => $this->event->id, 'area_id' => $this->cucina->id, 'role' => Role::AreaManager,
        ]);
        $bar = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id]);

        Supply::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'area_id' => $this->cucina->id, 'description' => 'In cucina']);
        Supply::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'area_id' => $bar->id, 'description' => 'Al bar']);
        Supply::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'area_id' => null, 'description' => 'Evento']);

        // Organizer: everything.
        $this->asOrganizer()->get('/forniture')->assertInertia(fn ($page) => $page->component('Manage/Forniture')->has('supplies', 3));

        // Manager: only their area's.
        $this->actingAs($manager)->withSession(['current_event_id' => $this->event->id])->get('/forniture')->assertInertia(
            fn ($page) => $page->has('supplies', 1)->where('supplies.0.description', 'In cucina')
        );
    }

    public function test_deleting_a_supplier_keeps_supplies_without_it()
    {
        $supplier = Supplier::factory()->for($this->tenant)->create();
        $supply = Supply::factory()->for($this->event)->create([
            'tenant_id' => $this->tenant->id, 'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($this->organizer)->delete("/suppliers/{$supplier->id}")->assertRedirect();

        $this->assertNotNull($supply->fresh());
        $this->assertNull($supply->fresh()->supplier_id);
    }

    public function test_a_plain_volunteer_cannot_reach_forniture()
    {
        $volunteer = Person::factory()->for($this->tenant)->create();

        $this->actingAs($volunteer)->get('/forniture')->assertForbidden();
        $this->actingAs($volunteer)->post('/suppliers', ['name' => 'X'])->assertForbidden();
    }
}
