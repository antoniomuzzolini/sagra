<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Event;
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
            'event_id' => Event::factory(),
            'role' => Role::Organizer,
            'area_id' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PersonRole $personRole) {
            $personRole->tenant_id ??= $personRole->person->tenant_id;
        });
    }
}
