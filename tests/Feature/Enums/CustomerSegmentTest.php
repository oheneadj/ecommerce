<?php

/**
 * Covers CustomerSegment::apply() — each canned segment used to target the
 * customer-broadcast-notifications admin page.
 */

declare(strict_types=1);

namespace Tests\Feature\Enums;

use App\Enums\CustomerSegment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerSegmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_returns_every_customer_unfiltered(): void
    {
        User::factory()->count(3)->create();

        $ids = CustomerSegment::All->apply(User::query()->customers())->pluck('id');

        $this->assertCount(3, $ids);
    }

    public function test_has_ordered_only_includes_customers_with_an_order(): void
    {
        $withOrder = User::factory()->create();
        Order::factory()->create(['user_id' => $withOrder->id]);
        $withoutOrder = User::factory()->create();

        $ids = CustomerSegment::HasOrdered->apply(User::query()->customers())->pluck('id');

        $this->assertTrue($ids->contains($withOrder->id));
        $this->assertFalse($ids->contains($withoutOrder->id));
    }

    public function test_never_ordered_only_includes_customers_without_an_order(): void
    {
        $withOrder = User::factory()->create();
        Order::factory()->create(['user_id' => $withOrder->id]);
        $withoutOrder = User::factory()->create();

        $ids = CustomerSegment::NeverOrdered->apply(User::query()->customers())->pluck('id');

        $this->assertFalse($ids->contains($withOrder->id));
        $this->assertTrue($ids->contains($withoutOrder->id));
    }

    public function test_joined_recently_excludes_customers_older_than_thirty_days(): void
    {
        $recent = User::factory()->create(['created_at' => Carbon::now()->subDays(5)]);
        $old = User::factory()->create(['created_at' => Carbon::now()->subDays(60)]);

        $ids = CustomerSegment::JoinedRecently->apply(User::query()->customers())->pluck('id');

        $this->assertTrue($ids->contains($recent->id));
        $this->assertFalse($ids->contains($old->id));
    }
}
