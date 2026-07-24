<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'type' => StockMovementType::Restock,
            'quantity' => fake()->numberBetween(1, 50),
            'note' => null,
            'user_id' => null,
        ];
    }
}
