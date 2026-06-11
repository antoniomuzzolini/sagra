<?php

namespace Database\Factories;

use App\Enums\AreaFamily;
use App\Models\Area;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Cucina',
            'family' => AreaFamily::FoodService,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Area $area) {
            $area->tenant_id ??= $area->event->tenant_id;
        });
    }
}
