<?php

/**
 * Covers StaticPagePolicy — content/CMS pages, Admin/Super Admin only
 * (Store Keeper's role never extends to marketing content).
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\StaticPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaticPagePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_admin_and_super_admin_can_manage_static_pages(): void
    {
        $page = StaticPage::factory()->create();

        foreach ([UserRole::Admin, UserRole::SuperAdmin] as $role) {
            $user = $this->userWithRole($role);

            $this->assertTrue($user->can('viewAny', StaticPage::class));
            $this->assertTrue($user->can('create', StaticPage::class));
            $this->assertTrue($user->can('update', $page));
            $this->assertTrue($user->can('delete', $page));
        }
    }

    public function test_store_keeper_cannot_view_or_manage_static_pages(): void
    {
        $user = $this->userWithRole(UserRole::StoreKeeper);
        $page = StaticPage::factory()->create();

        $this->assertFalse($user->can('viewAny', StaticPage::class));
        $this->assertFalse($user->can('create', StaticPage::class));
        $this->assertFalse($user->can('update', $page));
        $this->assertFalse($user->can('delete', $page));
    }

    public function test_only_super_admin_can_force_delete_a_static_page(): void
    {
        $page = StaticPage::factory()->create();

        $this->assertTrue($this->userWithRole(UserRole::SuperAdmin)->can('forceDelete', $page));
        $this->assertFalse($this->userWithRole(UserRole::Admin)->can('forceDelete', $page));
    }
}
