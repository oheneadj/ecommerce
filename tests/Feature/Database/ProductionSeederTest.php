<?php

/**
 * Covers ProductionSeeder — the safe seeder set for a real client
 * deployment (Epic E13.3), as opposed to DatabaseSeeder's full local-dev
 * set which includes fake demo users/catalog/orders.
 */

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\ShippingMethod;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_roles_store_settings_and_shipping_methods(): void
    {
        (new ProductionSeeder)->run();

        foreach (UserRole::cases() as $role) {
            $this->assertTrue(Role::where('name', $role->value)->exists());
        }

        $this->assertNotNull(StoreSetting::current()->business_name);
        $this->assertGreaterThan(0, ShippingMethod::query()->count());
    }

    public function test_it_creates_no_fake_demo_users_or_orders(): void
    {
        (new ProductionSeeder)->run();

        $this->assertSame(0, User::query()->count());
    }
}
