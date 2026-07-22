<?php

namespace Tests\Feature\Volunteer;

use App\Enums\Role;
use App\Enums\SignupStatus;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Tenant;
use App\Notifications\AvailabilityReceived;
use App\Notifications\ShiftReminder;
use App\Notifications\SubstitutionRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(): array
    {
        return [
            'endpoint' => 'https://push.example.com/sub/abc123',
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-token'],
        ];
    }

    public function test_a_volunteer_can_register_a_push_subscription()
    {
        $person = Person::factory()->create();

        $this->actingAs($person)
            ->post('/me/push-subscriptions', $this->subscription())
            ->assertRedirect();

        $this->assertSame(1, $person->pushSubscriptions()->count());
    }

    public function test_a_subscription_can_be_removed()
    {
        $person = Person::factory()->create();
        $this->actingAs($person)->post('/me/push-subscriptions', $this->subscription());

        $this->actingAs($person)
            ->delete('/me/push-subscriptions', ['endpoint' => 'https://push.example.com/sub/abc123'])
            ->assertRedirect();

        $this->assertSame(0, $person->pushSubscriptions()->count());
    }

    public function test_the_reminder_prefers_push_and_falls_back_to_mail()
    {
        $event = Event::factory()->create();
        $area = Area::factory()->for($event)->create(['tenant_id' => $event->tenant_id]);
        $shift = Shift::factory()->for($area)->create(['tenant_id' => $event->tenant_id]);
        $reminder = new ShiftReminder($shift);

        $pushOnly = Person::factory()->create(['email' => null]);
        $pushOnly->updatePushSubscription('https://push.example.com/a', 'k', 't');
        $mailOnly = Person::factory()->create(['email' => 'maria@example.com']);
        $unreachable = Person::factory()->create(['email' => null]);

        $this->assertSame([WebPushChannel::class], $reminder->via($pushOnly));
        $this->assertSame(['mail'], $reminder->via($mailOnly));
        $this->assertSame([], $reminder->via($unreachable));
    }

    public function test_managers_are_notified_when_someone_volunteers()
    {
        Notification::fake();
        [$manager, $shift] = $this->areaWithManager();
        $volunteer = Person::factory()->create(['tenant_id' => $shift->tenant_id]);

        $this->actingAs($volunteer)->post("/me/shifts/{$shift->id}/signup");

        Notification::assertSentTo($manager, AvailabilityReceived::class);

        // Tapping twice stays idempotent: no second notification.
        $this->actingAs($volunteer)->post("/me/shifts/{$shift->id}/signup");
        Notification::assertSentToTimes($manager, AvailabilityReceived::class, 1);
    }

    public function test_a_manager_volunteering_in_their_own_area_is_not_self_notified()
    {
        Notification::fake();
        [$manager, $shift] = $this->areaWithManager();

        $this->actingAs($manager)->post("/me/shifts/{$shift->id}/signup");

        Notification::assertNotSentTo($manager, AvailabilityReceived::class);
    }

    public function test_managers_are_notified_of_substitution_requests_once()
    {
        Notification::fake();
        [$manager, $shift] = $this->areaWithManager();
        $volunteer = Person::factory()->create(['tenant_id' => $shift->tenant_id]);
        ShiftSignup::factory()->for($shift)->for($volunteer)->create(['status' => SignupStatus::Assigned]);

        $this->actingAs($volunteer)->post("/me/shifts/{$shift->id}/substitution");
        $this->actingAs($volunteer)->post("/me/shifts/{$shift->id}/substitution");

        Notification::assertSentToTimes($manager, SubstitutionRequested::class, 1);
    }

    public function test_the_manifest_is_served()
    {
        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertJsonPath('name', config('app.name'))
            ->assertJsonPath('start_url', '/me');
    }

    /**
     * @return array{0: Person, 1: Shift}
     */
    private function areaWithManager(): array
    {
        $tenant = Tenant::factory()->create();
        $event = Event::factory()->create(['tenant_id' => $tenant->id]);
        $area = Area::factory()->for($event)->create(['tenant_id' => $tenant->id]);
        $shift = Shift::factory()->for($area)->create(['tenant_id' => $tenant->id]);

        // Reachable by email: a notification without channels would be
        // skipped entirely (also by the notification fake).
        $manager = Person::factory()->create(['tenant_id' => $tenant->id, 'email' => 'manager@example.com']);
        PersonRole::factory()->for($manager)->for($event)->create([
            'tenant_id' => $tenant->id,
            'role' => Role::AreaManager,
            'area_id' => $area->id,
        ]);

        return [$manager, $shift];
    }
}
