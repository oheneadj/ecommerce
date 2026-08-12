<?php

/**
 * Covers the panel-level access boundary itself (User::canAccessPanel()),
 * separate from StoreKeeperAccessTest's per-resource role checks — a
 * customer must never reach /admin under any circumstance, and staff login
 * must keep working the same way regardless of which customer-facing auth
 * methods (phone OTP, Google) happen to also be set on their record.
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_with_no_staff_role_cannot_access_the_admin_panel(): void
    {
        $customer = User::factory()->create(['phone' => '+233201234567', 'password' => null]);

        $this->assertFalse($customer->canAccessPanel(filament()->getCurrentPanel() ?? filament()->getDefaultPanel()));

        $this->actingAs($customer)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_a_customer_who_also_has_a_google_identity_still_cannot_access_the_admin_panel(): void
    {
        $customer = User::factory()->create(['google_id' => 'google-123', 'password' => null]);

        $this->actingAs($customer)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_login_is_unaffected_by_the_customer_having_phone_and_google_identifiers_set(): void
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $admin = User::factory()->create([
            'phone' => '+233209999999',
            'google_id' => 'google-999',
        ]);
        $admin->assignRole(UserRole::Admin->value);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertSuccessful();
    }
}
