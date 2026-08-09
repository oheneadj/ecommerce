<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Deliberately does NOT use WithoutModelEvents — this app relies on model
 * events for the HasUlid trait (ulid generation on creating) and
 * App\Observers\OrderObserver (order_number generation on creating).
 * Disabling model events during seeding would leave those NOT NULL unique
 * columns empty and fail every insert.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            StoreSettingSeeder::class,
            ShippingMethodSeeder::class,
            CatalogSeeder::class,
            CouponSeeder::class,
            OrderSeeder::class,
            HistoricalDataSeeder::class,
        ]);
    }
}
