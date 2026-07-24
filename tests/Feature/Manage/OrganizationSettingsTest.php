<?php

namespace Tests\Feature\Manage;

use App\Enums\SignupStatus;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use App\Notifications\SeatFreed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_organizer_edits_the_organization_settings()
    {
        $organizer = Person::factory()->organizer()->for(Tenant::factory())->create();

        $this->actingAs($organizer)->get('/organization')->assertOk()->assertInertia(
            fn ($page) => $page->component('Manage/Organization')
                ->where('organization.notifySeatFreed', true) // default: on
        );

        $this->actingAs($organizer)->put('/organization', [
            'name' => 'Pro Loco Rinominata',
            'notify_seat_freed' => false,
            'notify_new_shifts' => false,
        ])->assertSessionHasNoErrors();

        $tenant = $organizer->tenant->fresh();
        $this->assertSame('Pro Loco Rinominata', $tenant->name);
        $this->assertFalse($tenant->setting('notify_seat_freed', true));
        $this->assertFalse($tenant->setting('notify_new_shifts', true));
    }

    public function test_non_organizers_cannot_touch_the_settings()
    {
        $volunteer = Person::factory()->create();

        $this->actingAs($volunteer)->get('/organization')->assertForbidden();
        $this->actingAs($volunteer)->put('/organization', ['name' => 'X', 'notify_seat_freed' => false, 'notify_new_shifts' => false])->assertForbidden();
    }

    /**
     * @return array{0: Person, 1: Shift, 2: Person}
     */
    private function coveredShiftWithAreaVolunteer(): array
    {
        $tenant = Tenant::factory()->create();
        $organizer = Person::factory()->organizer()->for($tenant)->create();
        $event = Event::factory()->for($tenant)->create();
        $area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);
        $shift = Shift::factory()->for($area)->create([
            'tenant_id' => $tenant->id,
            'starts_at' => now()->addDays(3)->setTime(18, 0),
            'ends_at' => now()->addDays(3)->setTime(22, 0),
            'needed_people' => 1,
        ]);

        // The person covering the seat.
        $assigned = Person::factory()->create(['tenant_id' => $tenant->id]);
        ShiftSignup::factory()->for($shift)->for($assigned)->create(['tenant_id' => $tenant->id, 'status' => SignupStatus::Assigned]);

        // A volunteer with history in the area (another shift), reachable by email.
        $areaVolunteer = Person::factory()->create(['tenant_id' => $tenant->id, 'email' => 'storica@example.com']);
        $otherShift = Shift::factory()->for($area)->create(['tenant_id' => $tenant->id, 'starts_at' => now()->addDays(4)->setTime(18, 0)]);
        ShiftSignup::factory()->for($otherShift)->for($areaVolunteer)->create(['tenant_id' => $tenant->id, 'status' => SignupStatus::Assigned]);

        return [$organizer, $shift, $areaVolunteer];
    }

    public function test_freeing_a_seat_notifies_the_areas_volunteers()
    {
        Notification::fake();
        [$organizer, $shift, $areaVolunteer] = $this->coveredShiftWithAreaVolunteer();
        $signup = $shift->signups()->first();

        $this->actingAs($organizer)->delete("/signups/{$signup->id}")->assertRedirect();

        Notification::assertSentTo($areaVolunteer, SeatFreed::class);
    }

    public function test_the_seat_freed_broadcast_can_be_turned_off()
    {
        Notification::fake();
        [$organizer, $shift] = $this->coveredShiftWithAreaVolunteer();
        $organizer->tenant->update(['settings' => ['notify_seat_freed' => false]]);
        $signup = $shift->signups()->first();

        $this->actingAs($organizer)->delete("/signups/{$signup->id}");

        Notification::assertNothingSent();
    }
}
