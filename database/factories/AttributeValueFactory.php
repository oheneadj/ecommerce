<?php

namespace Database\Factories;

use App\Models\AttributeValue;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeValue>
 */
class AttributeValueFactory extends Factory
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
            'attribute_name' => fake()->randomElement(['Size', 'Color']),
            'value' => fake()->randomElement(['Small', 'Medium', 'Large', 'Red', 'Blue', 'Black']),
        ];
    }
}
