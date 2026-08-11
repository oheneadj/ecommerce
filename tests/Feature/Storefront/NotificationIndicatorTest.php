<?php

/**
 * Covers the header notification bell — unread count, preview dropdown,
 * and mark-as-read-on-open.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\NotificationIndicator;
use App\Models\User;
use App\Notifications\CustomerBroadcastNotification;
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

    public function test_opening_the_dropdown_marks_the_shown_notifications_as_read(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $customer->notify(new CustomerBroadcastNotification('Sale!', 'Everything is 20% off.'));

        Livewire::test(NotificationIndicator::class)->call('toggle');

        $this->assertNotNull($customer->notifications()->first()->read_at);
    }
}
