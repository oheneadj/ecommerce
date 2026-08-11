<?php

/**
 * Covers User::scopeCustomers() — the single "is this a customer, not
 * staff" filter reused by the Customers admin resource and customer
 * broadcast targeting.
 */

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_scope_excludes_accounts_holding_any_staff_role(): void
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');

        $customer = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        $ids = User::query()->customers()->pluck('id');

        $this->assertTrue($ids->contains($customer->id));
        $this->assertFalse($ids->contains($admin->id));
    }
}
