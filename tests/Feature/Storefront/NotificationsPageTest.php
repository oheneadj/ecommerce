<?php

/**
 * Covers the customer-facing full notification history page.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\NotificationsPage;
use App\Models\User;
use App\Notifications\CustomerBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $this->get('/account/notifications')->assertRedirect();
    }

    public function test_it_lists_the_customers_notifications(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $customer->notify(new CustomerBroadcastNotification('Sale!', 'Everything is 20% off.'));

        // #[Lazy] means the real component only renders past its own
        // `$refresh` follow-up request — same forced-hydration pattern
        // CartPageTest uses for its own #[Lazy] component.
        Livewire::test(NotificationsPage::class)
            ->call('$refresh')
            ->assertSee('Sale!')
            ->assertSee('Everything is 20% off.');
    }

    public function test_it_never_shows_another_customers_notifications(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $other->notify(new CustomerBroadcastNotification('Not for you', 'Secret message.'));
        $this->actingAs($customer);

        Livewire::test(NotificationsPage::class)->call('$refresh')->assertDontSee('Not for you');
    }

    /**
     * The real content only ever reaches the page through a follow-up
     * request the #[Lazy] attribute defers to — the initial HTTP response
     * (what a customer's very first paint actually sees) must show the
     * skeleton, never a blank gap while that request is in flight.
     */
    public function test_the_page_shows_a_skeleton_placeholder_before_the_real_component_loads(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/account/notifications')->assertOk()->assertSeeHtml('animate-pulse');
    }

    public function test_viewing_the_page_marks_notifications_as_read(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $customer->notify(new CustomerBroadcastNotification('Sale!', 'Everything is 20% off.'));

        // mount() (where the mark-as-read side effect lives) is only ever
        // invoked through the real #[Lazy] component's special internal
        // __lazyLoad call, never by a generic `$refresh` — disabling lazy
        // loading for this one test is Livewire's own documented way to
        // exercise mount() directly.
        Livewire::withoutLazyLoading();
        Livewire::test(NotificationsPage::class);

        $this->assertNotNull($customer->notifications()->first()->read_at);
    }
}
