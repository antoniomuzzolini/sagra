<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['Panino salamella', 'Patatine', 'Birra media', 'Acqua', 'Gnocco fritto']),
            'price' => fake()->randomFloat(2, 1, 8),
            'active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Product $product) {
            $product->tenant_id ??= $product->event?->tenant_id;
        });
    }
}
