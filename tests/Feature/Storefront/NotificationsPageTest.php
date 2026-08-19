<?php

/**
 * Covers the customer-facing full notification history page.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\NotificationsPage;
use App\Models\Order;
use App\Models\User;
use App\Notifications\BackupFailed;
use App\Notifications\CustomerBroadcastNotification;
use App\Notifications\OrderPlaced;
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
            ->assertSee('Sale!');
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

    public function test_viewing_the_page_does_not_mark_notifications_as_read(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $customer->notify(new CustomerBroadcastNotification('Sale!', 'Everything is 20% off.'));

        Livewire::test(NotificationsPage::class)->call('$refresh');

        $this->assertNull($customer->notifications()->first()->read_at);
    }

    public function test_clicking_a_non_order_notification_marks_it_read_and_expands_it_in_place(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $customer->notify(new CustomerBroadcastNotification('Sale!', 'Everything is 20% off.'));
        $notificationId = $customer->notifications()->first()->id;

        Livewire::test(NotificationsPage::class)
            ->call('$refresh')
            ->call('openNotification', $notificationId)
            ->assertSet('expandedNotificationId', $notificationId)
            ->assertSee('Everything is 20% off.');

        $this->assertNotNull($customer->notifications()->first()->read_at);
    }

    /**
     * A Super Admin account is still a plain `User` row, so it can log
     * into the customer-facing storefront too. Staff-only alerts (a
     * failed backup, a critical health check) must never surface in this
     * customer-facing view — they belong in the Filament admin bell only.
     */
    public function test_staff_only_notifications_never_appear_on_the_customer_facing_page(): void
    {
        $superAdmin = User::factory()->create();
        $this->actingAs($superAdmin);
        $superAdmin->notify(new BackupFailed('Some\\Exception\\Class'));

        Livewire::test(NotificationsPage::class)
            ->call('$refresh')
            ->assertDontSee('Backup failed');
    }

    public function test_clicking_an_order_notification_marks_it_read_and_navigates_to_the_order(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $customer->notify(new OrderPlaced($order));
        $notificationId = $customer->notifications()->first()->id;

        Livewire::test(NotificationsPage::class)
            ->call('$refresh')
            ->call('openNotification', $notificationId)
            ->assertRedirect(route('account.orders.show', $order));

        $this->assertNotNull($customer->notifications()->first()->read_at);
    }
}
