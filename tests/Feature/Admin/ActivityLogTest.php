<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function staff(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_updating_a_category_records_an_activity_log_entry(): void
    {
        $admin = $this->staff(UserRole::Admin);
        $this->actingAs($admin);

        $category = Category::factory()->create(['name' => 'Original']);
        $category->update(['name' => 'Renamed']);

        $activity = Activity::query()->where('subject_id', $category->id)->where('subject_type', Category::class)->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame('updated', $activity->event);
    }

    public function test_a_no_op_save_does_not_create_a_log_entry(): void
    {
        $category = Category::factory()->create(['name' => 'Same']);
        $countBefore = Activity::query()->count();

        $category->update(['name' => 'Same']);

        $this->assertSame($countBefore, Activity::query()->count());
    }

    public function test_only_super_admin_can_view_the_activity_log(): void
    {
        $superAdmin = $this->staff(UserRole::SuperAdmin);
        $admin = $this->staff(UserRole::Admin);
        $storeKeeper = $this->staff(UserRole::StoreKeeper);

        $this->actingAs($superAdmin)->get('/admin/activity-logs')->assertSuccessful();
        $this->actingAs($admin)->get('/admin/activity-logs')->assertForbidden();
        $this->actingAs($storeKeeper)->get('/admin/activity-logs')->assertForbidden();
    }
}
