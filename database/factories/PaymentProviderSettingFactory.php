<?php

namespace Database\Factories;

use App\Enums\PaymentProvider;
use App\Models\PaymentProviderSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProviderSetting>
 */
class PaymentProviderSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(PaymentProvider::cases())->value,
            'enabled' => false,
            'sort_order' => 0,
        ];
    }
}
