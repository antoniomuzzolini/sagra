<?php

namespace Database\Factories;

use App\Enums\Module;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Sagra '.fake()->year(),
            // A factory event is a fully equipped edition; the app's own
            // default for a newly created event is shifts only (D21).
            'enabled_modules' => array_column(Module::cases(), 'value'),
        ];
    }

    /**
     * An edition running only the given modules.
     */
    public function withModules(Module ...$modules): static
    {
        return $this->state(['enabled_modules' => array_map(fn (Module $m) => $m->value, $modules)]);
    }
}
