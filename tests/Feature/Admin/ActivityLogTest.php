<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
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

    /**
     * The package's LogsActivity trait records the before/after diff in
     * `attribute_changes` (`old`/`attributes` keys) — a different column
     * from `properties`, which is reserved for extra context added via
     * tapActivity()/withProperties() and never holds the automatic diff.
     */
    public function test_creating_a_record_logs_only_new_attribute_values_with_no_old_values(): void
    {
        Category::factory()->create(['name' => 'Brand New']);

        $activity = Activity::query()->where('event', 'created')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('Brand New', $activity->attribute_changes['attributes']['name'] ?? null);
        $this->assertArrayNotHasKey('old', (array) $activity->attribute_changes);
    }

    public function test_updating_a_record_logs_only_the_dirty_fields_before_and_after(): void
    {
        $category = Category::factory()->create(['name' => 'Original', 'slug' => 'original']);
        $category->update(['name' => 'Renamed']);

        $activity = Activity::query()->where('event', 'updated')->where('subject_id', $category->id)->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame(['name' => 'Original'], $activity->attribute_changes['old']);
        $this->assertSame(['name' => 'Renamed'], $activity->attribute_changes['attributes']);
    }

    public function test_deleting_a_record_logs_its_final_attribute_values_as_old(): void
    {
        $category = Category::factory()->create(['name' => 'About To Go']);
        $category->delete();

        $activity = Activity::query()->where('event', 'deleted')->where('subject_id', $category->id)->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('About To Go', $activity->attribute_changes['old']['name'] ?? null);
    }

    public function test_the_view_changes_modal_correctly_renders_before_and_after_values(): void
    {
        $category = Category::factory()->create(['name' => 'Original']);
        $category->update(['name' => 'Renamed']);

        $activity = Activity::query()->where('event', 'updated')->where('subject_id', $category->id)->latest('id')->first();

        $method = new ReflectionMethod(ActivityLogsTable::class, 'formatChanges');
        $method->setAccessible(true);
        $formatted = $method->invoke(null, $activity);

        $this->assertSame('"Original" → "Renamed"', $formatted['name']);
    }
}
