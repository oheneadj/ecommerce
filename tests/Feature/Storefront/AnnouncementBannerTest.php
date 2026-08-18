<?php

/**
 * Covers the storefront announcement banner — targeting, scheduling, and
 * priority. Deliberately not dismissible (see AnnouncementBanner's own
 * docblock for why) — a visitor sees it on every matching visit for as
 * long as it's running.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\GetCurrentCart;
use App\Enums\CustomerSegment;
use App\Livewire\Storefront\AnnouncementBanner;
use App\Models\Announcement;
use App\Models\AnnouncementView;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_a_currently_running_all_audience_announcement(): void
    {
        Announcement::factory()->create(['title' => 'Big Sale', 'audience' => CustomerSegment::All]);

        $this->get('/')->assertSee('Big Sale');
    }

    public function test_a_guest_never_sees_an_announcement_targeted_at_a_customer_segment(): void
    {
        Announcement::factory()->create(['title' => 'Welcome back', 'audience' => CustomerSegment::HasOrdered]);

        $this->get('/')->assertDontSee('Welcome back');
    }

    public function test_a_customer_matching_the_targeted_segment_sees_it(): void
    {
        $user = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id]);
        Announcement::factory()->create(['title' => 'Thanks for your order', 'audience' => CustomerSegment::HasOrdered]);
        $this->actingAs($user);

        Livewire::test(AnnouncementBanner::class)->assertSee('Thanks for your order');
    }

    public function test_a_customer_not_matching_the_targeted_segment_never_sees_it(): void
    {
        $user = User::factory()->create();
        Announcement::factory()->create(['title' => 'Thanks for your order', 'audience' => CustomerSegment::HasOrdered]);
        $this->actingAs($user);

        Livewire::test(AnnouncementBanner::class)->assertDontSee('Thanks for your order');
    }

    public function test_an_announcement_that_has_not_started_yet_is_not_shown(): void
    {
        Announcement::factory()->create(['title' => 'Coming soon', 'starts_at' => now()->addDay()]);

        $this->get('/')->assertDontSee('Coming soon');
    }

    public function test_an_expired_announcement_is_not_shown(): void
    {
        Announcement::factory()->create(['title' => 'Old news', 'starts_at' => now()->subDays(5), 'ends_at' => now()->subDay()]);

        $this->get('/')->assertDontSee('Old news');
    }

    public function test_an_inactive_announcement_is_not_shown_even_if_within_schedule(): void
    {
        Announcement::factory()->create(['title' => 'Turned off', 'active' => false]);

        $this->get('/')->assertDontSee('Turned off');
    }

    public function test_only_the_highest_priority_matching_announcement_is_shown(): void
    {
        Announcement::factory()->create(['title' => 'Low priority', 'priority' => 1]);
        Announcement::factory()->create(['title' => 'High priority', 'priority' => 10]);

        $response = $this->get('/');
        $response->assertSee('High priority');
        $response->assertDontSee('Low priority');
    }

    public function test_viewing_the_banner_records_a_view(): void
    {
        $announcement = Announcement::factory()->create();

        $this->get('/');

        $this->assertSame(1, AnnouncementView::query()->where('announcement_id', $announcement->id)->count());
    }

    public function test_the_same_visitor_still_sees_it_on_a_repeat_visit(): void
    {
        $user = User::factory()->create();
        Announcement::factory()->create(['title' => 'Still here']);
        $this->actingAs($user);

        Livewire::test(AnnouncementBanner::class)->assertSee('Still here');
        Livewire::test(AnnouncementBanner::class)->assertSee('Still here');
    }

    public function test_a_repeat_visit_does_not_record_a_second_view_row(): void
    {
        $user = User::factory()->create();
        $announcement = Announcement::factory()->create();
        $this->actingAs($user);

        Livewire::test(AnnouncementBanner::class);
        Livewire::test(AnnouncementBanner::class);

        $this->assertSame(1, AnnouncementView::query()->where('announcement_id', $announcement->id)->count());
    }

    public function test_the_banner_is_hidden_on_the_cart_page(): void
    {
        Announcement::factory()->create(['title' => 'Site-wide sale']);

        $this->get('/cart')->assertDontSee('Site-wide sale');
    }

    public function test_the_banner_is_hidden_on_the_checkout_page(): void
    {
        $user = User::factory()->create();
        Announcement::factory()->create(['title' => 'Site-wide sale']);
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 5]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);

        $this->get('/checkout')->assertOk()->assertDontSee('Site-wide sale');
    }
}
