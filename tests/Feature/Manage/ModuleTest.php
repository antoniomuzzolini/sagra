<?php

namespace Tests\Feature\Manage;

use App\Enums\Module;
use App\Models\Event;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Person $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->organizer = Person::factory()->organizer()->for($this->tenant)->create();
    }

    private function eventWithModules(Module ...$modules): Event
    {
        return Event::factory()->for($this->tenant)->withModules(...$modules)->create();
    }

    public function test_a_new_event_starts_with_shifts_only()
    {
        $this->actingAs($this->organizer)->post('/events', [
            'name' => 'Sagra nuova',
            'phases' => [['type' => 'service', 'starts_on' => '2027-07-01', 'ends_on' => '2027-07-03']],
        ])->assertRedirect();

        $event = Event::where('name', 'Sagra nuova')->firstOrFail();
        $this->assertSame(['shifts'], $event->enabledModules());
        $this->assertTrue($event->hasModule(Module::Shifts));
        $this->assertFalse($event->hasModule(Module::Orders));
    }

    public function test_a_disabled_module_answers_404()
    {
        $event = $this->eventWithModules(Module::Shifts);
        $session = ['current_event_id' => $event->id];

        $this->actingAs($this->organizer)->withSession($session)->get('/forniture')->assertNotFound();
        $this->actingAs($this->organizer)->withSession($session)->get('/cassa')->assertNotFound();
        $this->actingAs($this->organizer)->withSession($session)->get('/comande')->assertNotFound();
        // The one that is on still works.
        $this->actingAs($this->organizer)->withSession($session)->get('/manage/shifts')->assertOk();
    }

    public function test_enabling_a_module_opens_its_pages()
    {
        $event = $this->eventWithModules(Module::Shifts, Module::Supplies);

        $this->actingAs($this->organizer)->withSession(['current_event_id' => $event->id])
            ->get('/forniture')->assertOk();
    }

    public function test_writes_are_gated_too_not_just_pages()
    {
        $event = $this->eventWithModules(Module::Shifts);

        $this->actingAs($this->organizer)->withSession(['current_event_id' => $event->id])
            ->post('/suppliers', ['name' => 'Molino'])->assertNotFound();

        $this->assertDatabaseMissing('suppliers', ['name' => 'Molino']);
    }

    public function test_an_organizer_switches_modules_on_and_off()
    {
        $event = $this->eventWithModules(Module::Shifts);

        $this->actingAs($this->organizer)->put("/events/{$event->id}/modules", [
            'modules' => ['shifts', 'orders'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['shifts', 'orders'], $event->fresh()->enabledModules());

        // And off again: everything can be switched off.
        $this->actingAs($this->organizer)->put("/events/{$event->id}/modules", ['modules' => []])->assertRedirect();
        $this->assertSame([], $event->fresh()->enabledModules());
    }

    public function test_switching_a_module_off_keeps_its_data()
    {
        $event = $this->eventWithModules(Module::Shifts, Module::Supplies);
        $this->actingAs($this->organizer)->withSession(['current_event_id' => $event->id])
            ->post('/suppliers', ['name' => 'Molino']);

        $this->actingAs($this->organizer)->put("/events/{$event->id}/modules", ['modules' => ['shifts']]);

        // Hidden, not deleted — it comes back when the module is re-enabled.
        $this->assertDatabaseHas('suppliers', ['name' => 'Molino']);
        $this->actingAs($this->organizer)->put("/events/{$event->id}/modules", ['modules' => ['shifts', 'supplies']]);
        $this->actingAs($this->organizer)->withSession(['current_event_id' => $event->id])
            ->get('/forniture')->assertOk()->assertInertia(fn ($page) => $page->has('suppliers', 1));
    }

    public function test_an_unknown_module_key_is_rejected()
    {
        $event = $this->eventWithModules(Module::Shifts);

        $this->actingAs($this->organizer)->put("/events/{$event->id}/modules", [
            'modules' => ['shifts', 'teleportation'],
        ])->assertSessionHasErrors('modules.1');
    }

    public function test_only_the_owning_tenant_can_switch_modules()
    {
        $event = $this->eventWithModules(Module::Shifts);
        $stranger = Person::factory()->organizer()->for(Tenant::factory())->create();

        $this->actingAs($stranger)->put("/events/{$event->id}/modules", ['modules' => []])->assertNotFound();
        $this->assertSame(['shifts'], $event->fresh()->enabledModules());
    }

    public function test_the_sidebar_prop_lists_the_current_events_modules()
    {
        $event = $this->eventWithModules(Module::Shifts, Module::Orders);

        $this->actingAs($this->organizer)->withSession(['current_event_id' => $event->id])
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('modules', ['shifts', 'orders']));
    }

    public function test_the_event_page_does_not_shadow_the_shared_modules_prop()
    {
        $event = $this->eventWithModules(Module::Shifts, Module::Supplies);

        // The catalogue of switchable modules must not be named "modules", or
        // it would override the shared prop and empty the sidebar.
        $this->actingAs($this->organizer)->withSession(['current_event_id' => $event->id])
            ->get("/events/{$event->id}")
            ->assertInertia(fn ($page) => $page
                ->where('modules', ['shifts', 'supplies'])
                ->has('availableModules', 3)
            );
    }

    public function test_a_duplicated_edition_keeps_the_same_modules()
    {
        $event = $this->eventWithModules(Module::Shifts, Module::Supplies);
        $event->phases()->create(['tenant_id' => $this->tenant->id, 'type' => 'service', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-02']);

        $this->actingAs($this->organizer)->post("/events/{$event->id}/replicate", [
            'name' => 'Copia', 'starts_on' => '2027-07-01',
        ])->assertRedirect();

        $this->assertSame(['shifts', 'supplies'], Event::where('name', 'Copia')->firstOrFail()->enabledModules());
    }
}
