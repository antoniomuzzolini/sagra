<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addWeek()->setTime(18, 0);

        return [
            'area_id' => Area::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(4),
            'needed_people' => 4,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Shift $shift) {
            $shift->tenant_id ??= $shift->area->tenant_id;
        });
    }
}
