<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Area;
use App\Models\Person;
use App\Models\PersonRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonRole>
 */
class PersonRoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'role' => Role::AreaManager,
            'area_id' => Area::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PersonRole $personRole) {
            $personRole->tenant_id ??= $personRole->person->tenant_id;
            // An area manager role is event-scoped through its area (D19).
            $personRole->event_id ??= $personRole->area?->event_id;
        });
    }
}
