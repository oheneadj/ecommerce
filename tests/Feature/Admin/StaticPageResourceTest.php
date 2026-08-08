<?php

/**
 * Covers the Static Pages CMS admin resource (Epic E12.8) — backend/admin
 * only for now, no public route renders these yet.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\StaticPages\Pages\CreateStaticPage;
use App\Filament\Resources\StaticPages\Pages\EditStaticPage;
use App\Filament\Resources\StaticPages\Pages\ListStaticPages;
use App\Models\StaticPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaticPageResourceTest extends TestCase
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

    public function test_admin_can_list_static_pages(): void
    {
        $this->actingAs($this->admin());

        $page = StaticPage::factory()->create(['title' => 'About Us']);

        Livewire::test(ListStaticPages::class)
            ->assertCanSeeTableRecords([$page]);
    }

    public function test_admin_can_create_a_static_page(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateStaticPage::class)
            ->fillForm([
                'title' => 'About Us',
                'slug' => 'about-us',
                'content' => '<p>We sell great things.</p>',
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('static_pages', ['slug' => 'about-us', 'is_published' => true]);
    }

    public function test_creating_a_page_with_a_slug_that_already_exists_is_rejected(): void
    {
        $this->actingAs($this->admin());

        StaticPage::factory()->create(['slug' => 'about-us']);

        Livewire::test(CreateStaticPage::class)
            ->fillForm(['title' => 'About Us Too', 'slug' => 'about-us'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_admin_can_update_a_static_page(): void
    {
        $this->actingAs($this->admin());

        $page = StaticPage::factory()->create(['is_published' => false]);

        Livewire::test(EditStaticPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['is_published' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($page->fresh()->is_published);
    }

    public function test_store_keeper_cannot_access_the_resource(): void
    {
        $this->actingAs($this->storeKeeper());

        $this->assertFalse($this->storeKeeper()->can('viewAny', StaticPage::class));
    }
}
