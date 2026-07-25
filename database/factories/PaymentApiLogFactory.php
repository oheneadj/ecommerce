<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentApiLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentApiLog>
 */
class PaymentApiLogFactory extends Factory
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
            'provider' => 'moolre',
            'action' => 'initiate',
            'request_payload' => ['amount' => 1000],
            'response_payload' => ['status' => 'ok'],
            'status_code' => 200,
        ];
    }
}
