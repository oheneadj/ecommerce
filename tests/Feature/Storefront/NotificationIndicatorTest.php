<?php

/**
 * Covers the header notification bell — unread count, preview dropdown,
 * and mark-as-read-on-open.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\NotificationIndicator;
use App\Models\Order;
use App\Models\User;
use App\Notifications\BackupFailed;
use App\Notifications\CustomerBroadcastNotification;
use App\Notifications\OrderPlaced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_no_unread_count(): void
    {
        Livewire::test(NotificationIndicator::class)
            ->assertSet('open', false)
            ->assertSet('unreadCount', 0);
    }

    public function test_shows_the_unread_notification_count(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $customer->notify(new CustomerBroadcastNotification('Sale!', 'Everything is 20% off.'));

        Livewire::test(NotificationIndicator::class)->assertSee('1');
    }

    public function test_opening_the_dropdown_shows_recent_notifications(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $customer->notify(new CustomerBroadcastNotification('Sale!', 'Everything is 20% off.'));

        Livewire::test(NotificationIndicator::class)
            ->call('toggle')
            ->assertSee('Sale!')
            ->assertSee('Everything is 20% off.');
    }

    public function test_opening_the_dropdown_does_not_mark_notifications_as_read(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $customer->notify(new CustomerBroadcastNotification('Sale!', 'Everything is 20% off.'));

        Livewire::test(NotificationIndicator::class)->call('toggle');

        $this->assertNull($customer->notifications()->first()->read_at);
    }

    public function test_read_notifications_do_not_appear_in_the_preview(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $customer->notify(new CustomerBroadcastNotification('Sale!', 'Everything is 20% off.'));
        $customer->notifications()->first()->markAsRead();

        Livewire::test(NotificationIndicator::class)->assertDontSee('Sale!');
    }

    /**
     * A Super Admin account is still a plain `User` row and can log into
     * the storefront too — a staff-only alert (backup failure, health
     * check) must never inflate the customer-facing bell's unread count
     * or appear in its preview, since those belong in the Filament admin
     * bell only.
     */
    public function test_staff_only_notifications_are_excluded_from_the_unread_count_and_preview(): void
    {
        $superAdmin = User::factory()->create();
        $this->actingAs($superAdmin);
        $superAdmin->notify(new BackupFailed('Some\\Exception\\Class'));

        Livewire::test(NotificationIndicator::class)
            ->assertSet('unreadCount', 0)
            ->call('toggle')
            ->assertDontSee('Backup failed');
    }

    public function test_clicking_an_order_notification_in_the_preview_marks_it_read_and_navigates_to_the_order(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $customer->notify(new OrderPlaced($order));
        $notificationId = $customer->notifications()->first()->id;

        Livewire::test(NotificationIndicator::class)
            ->call('openNotification', $notificationId)
            ->assertRedirect(route('account.orders.show', $order));

        $this->assertNotNull($customer->notifications()->first()->read_at);
    }

    public function test_clicking_a_non_order_notification_in_the_preview_marks_it_read_and_expands_it_in_place(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $customer->notify(new CustomerBroadcastNotification('Sale!', 'Everything is 20% off.'));
        $notificationId = $customer->notifications()->first()->id;

        Livewire::test(NotificationIndicator::class)
            ->call('openNotification', $notificationId)
            ->assertNoRedirect()
            ->assertSet('expandedNotificationId', $notificationId);

        $this->assertNotNull($customer->notifications()->first()->read_at);
    }
}
