<?php

/**
 * Seeds a few coupons covering the different scope/expiry combinations.
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Models\Category;
use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        // Cart-wide, fixed amount.
        Coupon::factory()->create([
            'code' => 'WELCOME10',
            'type' => CouponType::Fixed,
            'value' => 1000,
            'usage_limit_per_user' => 1,
        ]);

        // Cart-wide, percentage.
        Coupon::factory()->create([
            'code' => 'SAVE15',
            'type' => CouponType::Percentage,
            'value' => 15,
            'min_order_amount' => 10000,
        ]);

        // Scoped to a category.
        $electronics = Category::query()->where('slug', 'electronics')->first();

        if ($electronics !== null) {
            $scoped = Coupon::factory()->create([
                'code' => 'TECH20',
                'type' => CouponType::Percentage,
                'value' => 20,
                'usage_limit' => 50,
            ]);
            $scoped->categories()->attach($electronics->id);
        }

        // Free shipping.
        Coupon::factory()->create([
            'code' => 'FREESHIP',
            'type' => CouponType::FreeShipping,
            'value' => null,
        ]);

        // Already expired — for testing the rejection path in the admin panel.
        Coupon::factory()->create([
            'code' => 'EXPIRED2025',
            'type' => CouponType::Fixed,
            'value' => 500,
            'expires_at' => now()->subMonth(),
        ]);

        // Inactive.
        Coupon::factory()->create([
            'code' => 'PAUSED',
            'type' => CouponType::Fixed,
            'value' => 500,
            'active' => false,
        ]);
    }
}
