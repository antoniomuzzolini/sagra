<?php

namespace Tests\Feature\Manage;

use App\Enums\Role;
use App\Enums\SignupStatus;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReplicationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Person $organizer;

    private Event $source;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->organizer = Person::factory()->organizer()->for($this->tenant)->create();

        // Source edition: prep phase then a service weekend.
        $this->source = Event::factory()->for($this->tenant)->create(['name' => 'Sagra 2026']);
        $this->source->phases()->create(['tenant_id' => $this->tenant->id, 'type' => 'preparation', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-03']);
        $this->source->phases()->create(['tenant_id' => $this->tenant->id, 'type' => 'service', 'starts_on' => '2026-07-04', 'ends_on' => '2026-07-05']);

        $this->area = Area::factory()->for($this->source)->create(['tenant_id' => $this->tenant->id, 'name' => 'Cucina', 'family' => 'food_service']);

        // A shift with a signup: the shift travels, the signup doesn't.
        $shift = Shift::factory()->for($this->area)->create([
            'tenant_id' => $this->tenant->id,
            'starts_at' => '2026-07-04 18:00',
            'ends_at' => '2026-07-04 22:00',
            'needed_people' => 6,
            'notes' => 'Portare i coltelli',
        ]);
        $volunteer = Person::factory()->for($this->tenant)->create();
        ShiftSignup::factory()->for($shift)->for($volunteer)->create(['tenant_id' => $this->tenant->id, 'status' => SignupStatus::Assigned]);
    }

    private function replicate(array $overrides = [])
    {
        return $this->actingAs($this->organizer)->post("/events/{$this->source->id}/replicate", array_merge([
            'name' => 'Sagra 2027',
            'starts_on' => '2027-07-01',
        ], $overrides));
    }

    public function test_it_copies_the_skeleton_onto_the_new_dates()
    {
        $this->replicate()->assertRedirect();

        $new = Event::where('name', 'Sagra 2027')->firstOrFail();
        $new->load('phases', 'areas.shifts');

        // Phases remapped by the whole-year offset, structure intact.
        $this->assertEqualsCanonicalizing(
            ['2027-07-01', '2027-07-04'],
            $new->phases->map(fn ($p) => $p->starts_on->toDateString())->all(),
        );
        $this->assertSame('2027-07-05', $new->endsOn()->toDateString());

        // Area copied with its family.
        $this->assertCount(1, $new->areas);
        $newArea = $new->areas->first();
        $this->assertSame('Cucina', $newArea->name);
        $this->assertSame('food_service', $newArea->family->value);

        // Shift copied, remapped, headcount and notes travel.
        $this->assertCount(1, $newArea->shifts);
        $newShift = $newArea->shifts->first();
        $this->assertSame('2027-07-04 18:00', $newShift->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2027-07-04 22:00', $newShift->ends_at->format('Y-m-d H:i'));
        $this->assertSame(6, $newShift->needed_people);
        $this->assertSame('Portare i coltelli', $newShift->notes);
    }

    public function test_availabilities_and_assignments_never_travel()
    {
        $this->replicate()->assertRedirect();

        $new = Event::where('name', 'Sagra 2027')->firstOrFail();
        $newShiftIds = Shift::whereIn('area_id', $new->areas()->pluck('id'))->pluck('id');

        $this->assertSame(0, ShiftSignup::whereIn('shift_id', $newShiftIds)->count());
    }

    public function test_area_managers_travel()
    {
        $manager = Person::factory()->for($this->tenant)->create();
        PersonRole::factory()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $manager->id,
            'event_id' => $this->source->id,
            'area_id' => $this->area->id,
            'role' => Role::AreaManager,
        ]);

        $this->replicate()->assertRedirect();

        $new = Event::where('name', 'Sagra 2027')->firstOrFail();
        $newArea = $new->areas()->first();

        $this->assertTrue(
            PersonRole::where('event_id', $new->id)->where('area_id', $newArea->id)->where('person_id', $manager->id)->exists()
        );
    }

    public function test_a_soft_deleted_managers_role_is_skipped()
    {
        $manager = Person::factory()->for($this->tenant)->create();
        PersonRole::factory()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $manager->id,
            'event_id' => $this->source->id,
            'area_id' => $this->area->id,
            'role' => Role::AreaManager,
        ]);
        $manager->delete();

        $this->replicate()->assertRedirect();

        $new = Event::where('name', 'Sagra 2027')->firstOrFail();
        $this->assertSame(0, PersonRole::where('event_id', $new->id)->count());
    }

    public function test_it_needs_a_name_and_a_start_date()
    {
        $this->replicate(['name' => '', 'starts_on' => ''])
            ->assertSessionHasErrors(['name', 'starts_on']);
    }

    public function test_only_the_owning_tenant_can_replicate()
    {
        $stranger = Person::factory()->organizer()->for(Tenant::factory())->create();

        $this->actingAs($stranger)->post("/events/{$this->source->id}/replicate", [
            'name' => 'Rubata',
            'starts_on' => '2027-07-01',
        ])->assertNotFound();

        $this->assertDatabaseMissing('events', ['name' => 'Rubata']);
    }

    public function test_a_non_organizer_cannot_replicate()
    {
        $volunteer = Person::factory()->for($this->tenant)->create();

        $this->actingAs($volunteer)->post("/events/{$this->source->id}/replicate", [
            'name' => 'Nope',
            'starts_on' => '2027-07-01',
        ])->assertForbidden();
    }
}
