<?php

namespace Tests\Feature\Core;

use App\Enums\PhaseType;
use App\Models\Area;
use App\Models\Event;
use App\Models\Phase;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftPhaseDerivationTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();

        Phase::factory()->for($this->event)->create([
            'type' => PhaseType::Preparation,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
        ]);
        Phase::factory()->for($this->event)->create([
            'type' => PhaseType::Service,
            'starts_on' => '2026-07-06',
            'ends_on' => '2026-07-08',
        ]);

        $this->area = Area::factory()->for($this->event)->create();
    }

    public function test_the_phase_of_a_shift_is_derived_from_its_start_date()
    {
        $setup = Shift::factory()->for($this->area)->create([
            'starts_at' => '2026-07-03 09:00:00',
            'ends_at' => '2026-07-03 12:00:00',
        ]);
        $dinner = Shift::factory()->for($this->area)->create([
            'starts_at' => '2026-07-06 18:00:00',
            'ends_at' => '2026-07-06 22:00:00',
        ]);

        $this->assertSame(PhaseType::Preparation, $setup->phase()->type);
        $this->assertSame(PhaseType::Service, $dinner->phase()->type);
    }

    public function test_a_shift_outside_any_phase_has_no_phase()
    {
        $shift = Shift::factory()->for($this->area)->create([
            'starts_at' => '2026-08-01 18:00:00',
            'ends_at' => '2026-08-01 22:00:00',
        ]);

        $this->assertNull($shift->phase());
    }

    public function test_a_shift_cannot_end_before_it_starts()
    {
        $this->expectException(\DomainException::class);

        Shift::factory()->for($this->area)->create([
            'starts_at' => '2026-07-06 22:00:00',
            'ends_at' => '2026-07-06 18:00:00',
        ]);
    }
}
