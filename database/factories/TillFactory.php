<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Till;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Till>
 */
class TillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['Cassa centrale', 'Cassa bar', 'Cassa panini']),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Till $till) {
            $till->tenant_id ??= $till->event?->tenant_id;
        });
    }
}
