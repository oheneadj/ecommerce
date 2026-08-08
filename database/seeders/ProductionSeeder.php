<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds only what a real client deployment needs to boot — roles, default
 * store settings/branding placeholders, and standard shipping methods.
 *
 * Deliberately excludes UserSeeder/CatalogSeeder/CouponSeeder/OrderSeeder —
 * those create fake demo accounts, products, and orders meant for local
 * dev only. The first real Super Admin is created via
 * `php artisan app:create-super-admin`, never this seeder (Epic E13.3).
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            StoreSettingSeeder::class,
            ShippingMethodSeeder::class,
        ]);
    }
}
