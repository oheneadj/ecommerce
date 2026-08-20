<?php

/**
 * Covers the admin Reviews resource's moderation and deletion actions.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    /**
     * Regression: `ForceDeleteBulkAction` checked a single batch-wide
     * `forceDeleteAny` ability (absent from `ReviewPolicy`, so Filament
     * defaulted to allow) instead of each record's own `forceDelete`
     * ability — `ReviewPolicy::forceDelete()` restricts this to Super
     * Admin only, but a plain Admin (who has `viewAny` on this resource)
     * could still permanently force-delete reviews via the bulk action.
     */
    public function test_admin_cannot_bulk_force_delete_reviews(): void
    {
        $this->actingAs($this->admin());

        $review = Review::factory()->create();
        $review->delete();

        Livewire::test(ListReviews::class)
            ->filterTable('trashed', ['value' => true])
            ->callTableBulkAction('forceDelete', [$review]);

        $this->assertModelExists($review);
    }
}
