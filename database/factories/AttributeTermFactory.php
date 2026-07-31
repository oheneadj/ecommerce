<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeTerm>
 */
class AttributeTermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $value = fake()->unique()->word();

        return [
            'attribute_id' => Attribute::factory(),
            'value' => ucfirst($value),
            'slug' => str($value)->slug(),
            'swatch_value' => null,
        ];
    }
}
