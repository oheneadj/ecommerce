<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SmsApiLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsApiLog>
 */
class SmsApiLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'moolre',
            'action' => 'otp',
            'recipient' => '0550000000',
            'request_payload' => ['recipient' => '0550000000', 'message' => 'Your login code is 123456. It expires in 10 minutes.'],
            'response_payload' => ['status' => 'ok'],
            'status_code' => 200,
        ];
    }
}
