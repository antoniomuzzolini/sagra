<?php

namespace Database\Factories;

use App\Enums\SupplyType;
use App\Models\Event;
use App\Models\Supply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supply>
 */
class SupplyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'type' => fake()->randomElement(SupplyType::cases()),
            'description' => fake()->words(3, true),
            'cost' => fake()->optional()->randomFloat(2, 5, 500),
            'acquired_on' => fake()->optional()->date(),
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Supply $supply) {
            $supply->tenant_id ??= $supply->event?->tenant_id;
        });
    }
}
