<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\SubArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubArea>
 */
class SubAreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'name' => fake()->randomElement(['Griglia', 'Friggitoria', 'Primi', 'Dolci']),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SubArea $subArea) {
            $subArea->tenant_id ??= $subArea->area?->tenant_id;
        });
    }
}
