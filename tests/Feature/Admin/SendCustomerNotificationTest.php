<?php

/**
 * Covers the "Send Notification" admin page — targeting resolution (all /
 * segment / specific), channel selection, and access control.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Pages\SendCustomerNotification;
use App\Jobs\FanOutCustomerBroadcast;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use ReflectionProperty;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SendCustomerNotificationTest extends TestCase
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

    public function test_store_keeper_cannot_access_the_page(): void
    {
        $this->actingAs($this->storeKeeper());

        $this->assertFalse(SendCustomerNotification::canAccess());
    }

    public function test_admin_can_access_the_page(): void
    {
        $this->actingAs($this->admin());

        $this->assertTrue(SendCustomerNotification::canAccess());
    }

    public function test_sending_to_all_customers_targets_every_customer(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());

        User::factory()->count(3)->create();

        Livewire::test(SendCustomerNotification::class)
            ->fillForm([
                'target' => 'all',
                'channels' => ['email'],
                'subject' => 'Sale!',
                'message' => 'Everything is 20% off.',
            ])
            ->call('send')
            ->assertHasNoFormErrors();

        Bus::assertDispatched(FanOutCustomerBroadcast::class, function (FanOutCustomerBroadcast $job): bool {
            return count($this->customerIdsFromJob($job)) === 3;
        });
    }

    public function test_sending_to_a_segment_only_targets_matching_customers(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());

        $withOrder = User::factory()->create();
        Order::factory()->create(['user_id' => $withOrder->id]);
        $withoutOrder = User::factory()->create();

        Livewire::test(SendCustomerNotification::class)
            ->fillForm([
                'target' => 'segment',
                'segment' => 'has_ordered',
                'channels' => ['email'],
                'subject' => 'Thanks for your order',
                'message' => 'Here is 10% off your next one.',
            ])
            ->call('send')
            ->assertHasNoFormErrors();

        Bus::assertDispatched(FanOutCustomerBroadcast::class, function (FanOutCustomerBroadcast $job) use ($withOrder, $withoutOrder): bool {
            $ids = $this->customerIdsFromJob($job);

            return in_array($withOrder->id, $ids, true) && ! in_array($withoutOrder->id, $ids, true);
        });
    }

    public function test_sending_to_specific_customers_only_targets_those_selected(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());

        $selected = User::factory()->create();
        $notSelected = User::factory()->create();

        Livewire::test(SendCustomerNotification::class)
            ->fillForm([
                'target' => 'specific',
                'customerIds' => [$selected->id],
                'channels' => ['email'],
                'subject' => 'Hi',
                'message' => 'A message just for you.',
            ])
            ->call('send')
            ->assertHasNoFormErrors();

        Bus::assertDispatched(FanOutCustomerBroadcast::class, function (FanOutCustomerBroadcast $job) use ($selected, $notSelected): bool {
            $ids = $this->customerIdsFromJob($job);

            return in_array($selected->id, $ids, true) && ! in_array($notSelected->id, $ids, true);
        });
    }

    public function test_at_least_one_channel_is_required(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());

        User::factory()->create();

        Livewire::test(SendCustomerNotification::class)
            ->fillForm([
                'target' => 'all',
                'channels' => [],
                'subject' => 'Sale!',
                'message' => 'Everything is 20% off.',
            ])
            ->call('send')
            ->assertHasFormErrors(['channels']);

        Bus::assertNotDispatched(FanOutCustomerBroadcast::class);
    }

    public function test_a_staff_user_never_appears_among_the_targeted_customers(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());

        Role::findOrCreate(UserRole::Admin->value, 'web');
        $otherStaff = User::factory()->create();
        $otherStaff->assignRole(UserRole::Admin->value);
        User::factory()->create();

        Livewire::test(SendCustomerNotification::class)
            ->fillForm([
                'target' => 'all',
                'channels' => ['email'],
                'subject' => 'Sale!',
                'message' => 'Everything is 20% off.',
            ])
            ->call('send')
            ->assertHasNoFormErrors();

        Bus::assertDispatched(FanOutCustomerBroadcast::class, function (FanOutCustomerBroadcast $job) use ($otherStaff): bool {
            return ! in_array($otherStaff->id, $this->customerIdsFromJob($job), true);
        });
    }

    /**
     * @return array<int, int>
     */
    private function customerIdsFromJob(FanOutCustomerBroadcast $job): array
    {
        $reflection = new ReflectionProperty($job, 'customerIds');

        return $reflection->getValue($job);
    }
}
