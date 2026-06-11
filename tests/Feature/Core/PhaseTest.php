<?php

namespace Tests\Feature\Core;

use App\Enums\PhaseType;
use App\Models\Event;
use App\Models\Phase;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_phases_of_the_same_event_cannot_overlap()
    {
        $event = Event::factory()->create();

        Phase::factory()->for($event)->create([
            'type' => PhaseType::Preparation,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
        ]);

        $this->expectException(DomainException::class);

        Phase::factory()->for($event)->create([
            'type' => PhaseType::Service,
            'starts_on' => '2026-07-05',
            'ends_on' => '2026-07-07',
        ]);
    }

    public function test_adjacent_phases_are_allowed()
    {
        $event = Event::factory()->create();

        Phase::factory()->for($event)->create([
            'type' => PhaseType::Preparation,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
        ]);

        $service = Phase::factory()->for($event)->create([
            'type' => PhaseType::Service,
            'starts_on' => '2026-07-06',
            'ends_on' => '2026-07-08',
        ]);

        $this->assertTrue($service->exists);
    }

    public function test_phases_of_different_events_may_overlap()
    {
        $first = Phase::factory()->create([
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
        ]);

        $second = Phase::factory()->create([
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
        ]);

        $this->assertTrue($second->exists);
        $this->assertNotSame($first->event_id, $second->event_id);
    }

    public function test_a_phase_cannot_end_before_it_starts()
    {
        $this->expectException(DomainException::class);

        Phase::factory()->create([
            'starts_on' => '2026-07-05',
            'ends_on' => '2026-07-01',
        ]);
    }

    public function test_event_dates_are_derived_from_phases()
    {
        $event = Event::factory()->create();

        Phase::factory()->for($event)->create([
            'type' => PhaseType::Preparation,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-03',
        ]);
        Phase::factory()->for($event)->create([
            'type' => PhaseType::Teardown,
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-11',
        ]);

        $event->refresh();

        $this->assertSame('2026-07-01', $event->startsOn()->toDateString());
        $this->assertSame('2026-07-11', $event->endsOn()->toDateString());
    }

    public function test_an_event_without_phases_has_no_dates()
    {
        $event = Event::factory()->create();

        $this->assertNull($event->startsOn());
        $this->assertNull($event->endsOn());
    }
}
