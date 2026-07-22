<?php

namespace Tests\Feature\Manage;

use App\Enums\Role;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Shift;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D19: area managers run their areas from the same sidebar shell as the
 * organizer, on scoped pages gated by the `manager` middleware.
 */
class ManagerPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Person, 1: Area, 2: Event}
     */
    private function managerWithArea(): array
    {
        $tenant = Tenant::factory()->create();
        $event = Event::factory()->for($tenant)->create();
        $area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id, 'name' => 'Cucina']);

        $manager = Person::factory()->withPassword()->for($tenant)->create();
        PersonRole::factory()->for($manager)->for($event)->create([
            'tenant_id' => $tenant->id,
            'role' => Role::AreaManager,
            'area_id' => $area->id,
        ]);

        return [$manager, $area, $event];
    }

    public function test_a_manager_opens_their_scoped_pages()
    {
        [$manager, $area] = $this->managerWithArea();
        Shift::factory()->for($area)->create([
            'tenant_id' => $area->tenant_id,
            'starts_at' => '2026-07-11 18:00',
            'ends_at' => '2026-07-11 22:00',
            'needed_people' => 3,
        ]);

        $this->actingAs($manager)->get('/manage/overview')->assertOk()->assertInertia(
            fn ($page) => $page->component('Manage/Overview')->has('areas', 1)->where('areas.0.name', 'Cucina')
        );

        $this->actingAs($manager)->get('/manage/calendar')->assertOk()->assertInertia(
            fn ($page) => $page->component('Manage/Calendar')->has('schedule.areas', 1)
        );

        $this->actingAs($manager)->get('/manage/people')->assertOk()->assertInertia(
            fn ($page) => $page->component('Manage/People')
        );
    }

    public function test_the_manager_pages_are_scoped_to_the_managed_areas()
    {
        [$manager, $area, $event] = $this->managerWithArea();
        // Another area of the same event the person does NOT manage.
        Area::factory()->for($event)->create(['tenant_id' => $area->tenant_id, 'name' => 'Bar']);

        $this->actingAs($manager)->get('/manage/overview')->assertInertia(
            fn ($page) => $page->has('areas', 1)->where('areas.0.name', 'Cucina')
        );
    }

    public function test_a_plain_volunteer_cannot_open_the_manager_pages()
    {
        $volunteer = Person::factory()->create();

        $this->actingAs($volunteer)->get('/manage/overview')->assertForbidden();
        $this->actingAs($volunteer)->get('/manage/calendar')->assertForbidden();
        $this->actingAs($volunteer)->get('/manage/people')->assertForbidden();
    }

    public function test_an_organizer_without_areas_cannot_open_the_manager_pages()
    {
        $organizer = Person::factory()->organizer()->create();

        $this->actingAs($organizer)->get('/manage/overview')->assertForbidden();
    }

    public function test_guests_are_sent_to_login()
    {
        $this->get('/manage/overview')->assertRedirect(route('login'));
    }
}
