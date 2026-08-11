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

        $this->get('/account/notifications')
            ->assertOk()
            ->assertSee('Sale!')
            ->assertSee('Everything is 20% off.');
    }

    public function test_it_never_shows_another_customers_notifications(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $other->notify(new CustomerBroadcastNotification('Not for you', 'Secret message.'));
        $this->actingAs($customer);

        $this->get('/account/notifications')->assertDontSee('Not for you');
    }

    public function test_viewing_the_page_marks_notifications_as_read(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $customer->notify(new CustomerBroadcastNotification('Sale!', 'Everything is 20% off.'));

        Livewire::test(NotificationsPage::class);

        $this->assertNotNull($customer->notifications()->first()->read_at);
    }
}
