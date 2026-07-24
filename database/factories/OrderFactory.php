<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'number' => fake()->unique()->numberBetween(1, 100000),
            'total' => fake()->randomFloat(2, 1, 50),
            'paid' => true,
            'payment_method' => 'cash',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Order $order) {
            $order->tenant_id ??= $order->event?->tenant_id;
        });
    }
}
