<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'item_snapshot' => [
                'product_name' => fake()->word().' '.fake()->word(),
                'sku' => strtoupper(fake()->bothify('SKU-####')),
            ],
            'unit_price' => fake()->numberBetween(500, 50000),
            'quantity' => fake()->numberBetween(1, 3),
        ];
    }
}
