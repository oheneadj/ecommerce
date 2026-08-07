<?php

/**
 * Covers ProductImagePolicy — mirrors ProductPolicy; every staff role can
 * view/create/update, but only Admin/Super Admin can delete.
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductImagePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_every_staff_role_can_view_create_and_update_product_images(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Admin, UserRole::StoreKeeper] as $role) {
            $user = $this->userWithRole($role);
            $image = ProductImage::factory()->create();

            $this->assertTrue($user->can('viewAny', ProductImage::class));
            $this->assertTrue($user->can('create', ProductImage::class));
            $this->assertTrue($user->can('update', $image));
        }
    }

    public function test_store_keeper_cannot_delete_a_product_image(): void
    {
        $user = $this->userWithRole(UserRole::StoreKeeper);
        $image = ProductImage::factory()->create();

        $this->assertFalse($user->can('delete', $image));
    }

    public function test_admin_and_super_admin_can_delete_a_product_image(): void
    {
        $image = ProductImage::factory()->create();

        $this->assertTrue($this->userWithRole(UserRole::Admin)->can('delete', $image));
        $this->assertTrue($this->userWithRole(UserRole::SuperAdmin)->can('delete', $image));
    }
}
