<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'email' => null,
        ];
    }

    /**
     * A person with a password account (D19): organizers and area managers.
     * Contactable by email, no phone required.
     */
    public function withPassword(): static
    {
        return $this->state(fn () => [
            'phone' => null,
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);
    }

    /**
     * A tenant-wide organizer (D19).
     */
    public function organizer(): static
    {
        return $this->withPassword()->state(fn () => [
            'is_organizer' => true,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
