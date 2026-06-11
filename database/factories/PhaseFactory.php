<?php

namespace Database\Factories;

use App\Enums\PhaseType;
use App\Models\Event;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phase>
 */
class PhaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'type' => PhaseType::Service,
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeek()->addDays(2)->toDateString(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Phase $phase) {
            $phase->tenant_id ??= $phase->event->tenant_id;
        });
    }
}
