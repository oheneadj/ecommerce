<?php

/**
 * Covers the Announcements admin resource — CRUD, authorization, and the
 * reach/dismiss counts the storefront banner feeds.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\CustomerSegment;
use App\Enums\UserRole;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Models\Announcement;
use App\Models\AnnouncementView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnnouncementResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function storeKeeper(): User
    {
        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::StoreKeeper->value);

        return $user;
    }

    public function test_admin_can_list_announcements(): void
    {
        $this->actingAs($this->admin());

        $announcement = Announcement::factory()->create(['title' => 'Big Sale']);

        Livewire::test(ListAnnouncements::class)
            ->assertCanSeeTableRecords([$announcement]);
    }

    public function test_admin_can_create_an_announcement(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateAnnouncement::class)
            ->fillForm([
                'title' => 'Big Sale',
                'body' => '20% off everything this weekend.',
                'audience' => CustomerSegment::All->value,
                'starts_at' => now(),
                'priority' => 5,
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('announcements', ['title' => 'Big Sale', 'priority' => 5]);
    }

    public function test_admin_can_update_an_announcement(): void
    {
        $this->actingAs($this->admin());

        $announcement = Announcement::factory()->create(['active' => true]);

        Livewire::test(EditAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->fillForm(['active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($announcement->fresh()->active);
    }

    public function test_the_list_page_shows_view_and_dismiss_counts(): void
    {
        $this->actingAs($this->admin());

        $announcement = Announcement::factory()->create();
        AnnouncementView::factory()->for($announcement)->create(['dismissed_at' => null]);
        AnnouncementView::factory()->for($announcement)->create(['dismissed_at' => now()]);

        Livewire::test(ListAnnouncements::class)
            ->assertTableColumnStateSet('views_count', 2, $announcement)
            ->assertTableColumnStateSet('dismissed_count', 1, $announcement);
    }

    public function test_store_keeper_cannot_access_the_resource(): void
    {
        $storeKeeper = $this->storeKeeper();

        $this->assertFalse($storeKeeper->can('viewAny', Announcement::class));
    }
}
