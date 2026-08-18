<?php

/**
 * Covers the storefront announcement popup — the dismissible counterpart
 * to AnnouncementBanner. Same targeting/scheduling/priority rules (see
 * App\Services\AnnouncementMatcher), but unlike a banner, a popup can be
 * closed and stays closed for that visitor.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\AnnouncementBanner;
use App\Livewire\Storefront\AnnouncementPopup;
use App\Models\Announcement;
use App\Models\AnnouncementView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementPopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_a_currently_running_popup(): void
    {
        Announcement::factory()->popup()->create(['title' => 'Welcome offer']);

        $this->get('/')->assertSee('Welcome offer');
    }

    public function test_a_banner_type_announcement_never_shows_in_the_popup_slot(): void
    {
        Announcement::factory()->create(['title' => 'Banner only']);

        Livewire::test(AnnouncementPopup::class)->assertDontSee('Banner only');
    }

    public function test_dismissing_hides_the_popup_and_never_shows_it_again(): void
    {
        $user = User::factory()->create();
        $announcement = Announcement::factory()->popup()->create(['title' => 'Dismiss me']);
        $this->actingAs($user);

        Livewire::test(AnnouncementPopup::class)
            ->assertSee('Dismiss me')
            ->call('dismiss')
            ->assertDontSee('Dismiss me');

        $view = AnnouncementView::query()->where('announcement_id', $announcement->id)->sole();
        $this->assertNotNull($view->dismissed_at);

        // A fresh component instance (e.g. a later page load) must still
        // never show it again — dismissal is permanent, not per-mount.
        Livewire::test(AnnouncementPopup::class)->assertDontSee('Dismiss me');
    }

    public function test_dismissing_one_customers_popup_never_affects_another_customer(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        Announcement::factory()->popup()->create(['title' => 'Shared popup']);

        $this->actingAs($customerA);
        Livewire::test(AnnouncementPopup::class)->call('dismiss');

        $this->actingAs($customerB);
        Livewire::test(AnnouncementPopup::class)->assertSee('Shared popup');
    }

    public function test_a_banner_and_a_popup_can_both_show_at_the_same_time(): void
    {
        Announcement::factory()->create(['title' => 'Site banner']);
        Announcement::factory()->popup()->create(['title' => 'Site popup']);

        Livewire::test(AnnouncementBanner::class)->assertSee('Site banner');
        Livewire::test(AnnouncementPopup::class)->assertSee('Site popup');
    }

    public function test_the_popup_is_hidden_on_the_cart_page(): void
    {
        Announcement::factory()->popup()->create(['title' => 'Site-wide offer']);

        $this->get('/cart')->assertDontSee('Site-wide offer');
    }
}
