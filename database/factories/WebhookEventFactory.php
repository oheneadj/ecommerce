<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'moolre',
            'event_id' => fake()->unique()->uuid(),
            'payload' => ['event' => 'payment.success'],
            'verified' => true,
            'processed_at' => null,
        ];
    }
}
