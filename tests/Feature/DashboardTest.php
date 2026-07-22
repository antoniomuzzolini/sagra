<?php

namespace Tests\Feature;

use App\Enums\SignupStatus;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = Person::factory()->organizer()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    private function organizer(): Person
    {
        return Person::factory()->organizer()->for(Tenant::factory())->create();
    }

    private function eventWithPhase(Person $user): Event
    {
        $event = Event::factory()->create(['tenant_id' => $user->tenant_id]);
        $event->phases()->create([
            'tenant_id' => $user->tenant_id,
            'type' => 'service',
            'starts_on' => now()->addDays(7)->toDateString(),
            'ends_on' => now()->addDays(9)->toDateString(),
        ]);

        return $event;
    }

    public function test_without_events_the_next_step_is_creating_one()
    {
        $this->actingAs($this->organizer())->get('/dashboard')->assertInertia(
            fn ($page) => $page->component('Dashboard')->where('nextStep', 'event')->where('event', null)
        );
    }

    public function test_an_event_without_areas_points_to_areas()
    {
        $user = $this->organizer();
        $event = $this->eventWithPhase($user);

        $this->actingAs($user)->get('/dashboard')->assertInertia(
            fn ($page) => $page->where('nextStep', 'areas')->where('event.id', $event->id)
        );
    }

    public function test_areas_without_upcoming_shifts_point_to_shifts()
    {
        $user = $this->organizer();
        $event = $this->eventWithPhase($user);
        Area::factory()->for($event)->create(['tenant_id' => $user->tenant_id]);

        $this->actingAs($user)->get('/dashboard')->assertInertia(
            fn ($page) => $page->where('nextStep', 'shifts')
        );
    }

    public function test_uncovered_shifts_and_queues_are_summarised()
    {
        $user = $this->organizer();
        $event = $this->eventWithPhase($user);
        $area = Area::factory()->for($event)->create(['tenant_id' => $user->tenant_id, 'name' => 'Cucina']);

        $uncovered = Shift::factory()->for($area)->create([
            'tenant_id' => $user->tenant_id,
            'starts_at' => now()->addDays(7)->setTime(18, 0),
            'ends_at' => now()->addDays(7)->setTime(22, 0),
            'needed_people' => 3,
        ]);
        $covered = Shift::factory()->for($area)->create([
            'tenant_id' => $user->tenant_id,
            'starts_at' => now()->addDays(8)->setTime(18, 0),
            'ends_at' => now()->addDays(8)->setTime(22, 0),
            'needed_people' => 1,
        ]);

        $assigned = Person::factory()->create(['tenant_id' => $user->tenant_id]);
        ShiftSignup::factory()->for($covered)->for($assigned)->create(['status' => SignupStatus::Assigned]);

        $pending = Person::factory()->create(['tenant_id' => $user->tenant_id]);
        ShiftSignup::factory()->for($uncovered)->for($pending)->create(['status' => SignupStatus::Available]);

        $substitute = Person::factory()->create(['tenant_id' => $user->tenant_id]);
        ShiftSignup::factory()->for($covered)->for($substitute)->create([
            'status' => SignupStatus::Assigned,
            'substitution_requested_at' => now(),
        ]);

        Person::factory()->create(['tenant_id' => $user->tenant_id, 'link_requested_at' => now()]);

        $this->actingAs($user)->get('/dashboard')->assertInertia(
            fn ($page) => $page
                ->where('nextStep', null)
                ->where('uncoveredCount', 1)
                ->has('uncoveredShifts', 1)
                ->where('uncoveredShifts.0.id', $uncovered->id)
                ->where('uncoveredShifts.0.missing', 3)
                ->where('pendingCount', 1)
                ->where('substitutionCount', 1)
                ->where('linkRequestsCount', 1)
                ->where('volunteersCount', 4)
        );
    }

    public function test_other_tenants_numbers_stay_out()
    {
        $user = $this->organizer();
        $this->eventWithPhase($user);
        Area::factory()->for(Event::factory())->create(); // foreign tenant
        Person::factory()->create(); // foreign person

        $this->actingAs($user)->get('/dashboard')->assertInertia(
            fn ($page) => $page->where('volunteersCount', 0)->where('nextStep', 'areas')
        );
    }
}
