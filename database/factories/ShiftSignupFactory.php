<?php

namespace Database\Factories;

use App\Enums\SignupStatus;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftSignup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftSignup>
 */
class ShiftSignupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'person_id' => Person::factory(),
            'status' => SignupStatus::Available,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ShiftSignup $signup) {
            $signup->tenant_id ??= $signup->shift->tenant_id;
        });
    }

    public function assigned(): static
    {
        return $this->state([
            'status' => SignupStatus::Assigned,
            'assigned_at' => now(),
        ]);
    }
}
