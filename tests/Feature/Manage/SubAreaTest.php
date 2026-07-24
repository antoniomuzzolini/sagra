<?php

namespace Tests\Feature\Manage;

use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\Shift;
use App\Models\SubArea;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubAreaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Person $organizer;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->organizer = Person::factory()->organizer()->for($this->tenant)->create();
        $event = Event::factory()->for($this->tenant)->create();
        $this->area = Area::factory()->for($event)->create(['tenant_id' => $this->tenant->id, 'name' => 'Cucina']);
    }

    public function test_an_organizer_manages_sub_areas()
    {
        $this->actingAs($this->organizer)->post("/areas/{$this->area->id}/sub-areas", ['name' => 'Griglia'])->assertRedirect();

        $sub = SubArea::where('area_id', $this->area->id)->firstOrFail();
        $this->assertSame('Griglia', $sub->name);
        $this->assertSame($this->tenant->id, $sub->tenant_id);

        $this->actingAs($this->organizer)->put("/sub-areas/{$sub->id}", ['name' => 'Griglia e forno'])->assertRedirect();
        $this->assertSame('Griglia e forno', $sub->fresh()->name);

        $this->actingAs($this->organizer)->delete("/sub-areas/{$sub->id}")->assertRedirect();
        $this->assertNull($sub->fresh());
    }

    public function test_a_non_organizer_cannot_manage_sub_areas()
    {
        $volunteer = Person::factory()->for($this->tenant)->create();

        $this->actingAs($volunteer)->post("/areas/{$this->area->id}/sub-areas", ['name' => 'Griglia'])->assertForbidden();
    }

    public function test_a_shift_can_belong_to_a_sub_area_of_its_area()
    {
        $sub = SubArea::factory()->for($this->area)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->organizer)->post("/areas/{$this->area->id}/shifts", [
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '22:00',
            'needed_people' => 4,
            'sub_area_id' => $sub->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($sub->id, Shift::where('area_id', $this->area->id)->firstOrFail()->sub_area_id);
    }

    public function test_a_shift_rejects_a_sub_area_of_another_area()
    {
        $otherArea = Area::factory()->for($this->area->event)->create(['tenant_id' => $this->tenant->id]);
        $foreignSub = SubArea::factory()->for($otherArea)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->organizer)->post("/areas/{$this->area->id}/shifts", [
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '22:00',
            'needed_people' => 4,
            'sub_area_id' => $foreignSub->id,
        ])->assertSessionHasErrors('sub_area_id');

        $this->assertSame(0, Shift::where('area_id', $this->area->id)->count());
    }

    public function test_deleting_a_sub_area_drops_its_shifts_to_area_level()
    {
        $sub = SubArea::factory()->for($this->area)->create(['tenant_id' => $this->tenant->id]);
        $shift = Shift::factory()->for($this->area)->create(['tenant_id' => $this->tenant->id, 'sub_area_id' => $sub->id]);

        $this->actingAs($this->organizer)->delete("/sub-areas/{$sub->id}")->assertRedirect();

        $this->assertNotNull($shift->fresh(), 'the shift survives');
        $this->assertNull($shift->fresh()->sub_area_id, 'it falls back to area-level');
    }

    public function test_the_areas_page_lists_sub_areas()
    {
        SubArea::factory()->for($this->area)->create(['tenant_id' => $this->tenant->id, 'name' => 'Friggitoria']);
        $this->actingAs($this->organizer)->withSession(['current_event_id' => $this->area->event_id]);

        $this->actingAs($this->organizer)->get('/manage/areas')->assertInertia(
            fn ($page) => $page->component('Manage/Areas')
                ->where('areas.0.subAreas.0.name', 'Friggitoria')
        );
    }
}
