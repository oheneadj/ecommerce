<?php

namespace Database\Factories;

use App\Enums\StockReservationStatus;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockReservation>
 */
class StockReservationFactory extends Factory
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
            'order_id' => null,
            'quantity' => fake()->numberBetween(1, 5),
            'status' => StockReservationStatus::Active,
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
