<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        ShippingMethod::factory()->create(['name' => 'Standard Delivery (3-5 days)', 'cost' => 1000]);
        ShippingMethod::factory()->create(['name' => 'Express Delivery (1-2 days)', 'cost' => 2500]);
        ShippingMethod::factory()->create(['name' => 'Store Pickup', 'cost' => 0]);
    }
}
