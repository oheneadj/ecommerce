<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\AdjustStockWithReservationCheck;
use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\ReleaseExpiredReservations;
use App\Actions\Inventory\ReserveStockForOrder;
use App\Enums\StockMovementType;
use App\Enums\StockReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidReservationQuantityException;
use App\Exceptions\InvalidStockMovementQuantityException;
use App\Exceptions\NegativeStockException;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_stock_movement_updates_the_variants_cached_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        RecordStockMovement::run($variant, StockMovementType::Restock, 5);

        $this->assertSame(15, $variant->fresh()->stock);
    }

    public function test_stock_movement_records_the_acting_user(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $user = User::factory()->create();

        $movement = RecordStockMovement::run($variant, StockMovementType::Damage, -2, $user, 'Dropped in warehouse');

        $this->assertSame($user->id, $movement->user_id);
        $this->assertSame(8, $variant->fresh()->stock);
    }

    public function test_recording_a_stock_movement_with_a_zero_quantity_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        $this->expectException(InvalidStockMovementQuantityException::class);

        RecordStockMovement::run($variant, StockMovementType::Adjustment, 0);
    }

    public function test_a_rejected_zero_quantity_movement_never_touches_stock_or_the_ledger(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        try {
            RecordStockMovement::run($variant, StockMovementType::Adjustment, 0);
        } catch (InvalidStockMovementQuantityException) {
            // expected
        }

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(0, $variant->stockMovements()->count());
    }

    public function test_a_movement_that_would_leave_stock_below_zero_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        $this->expectException(NegativeStockException::class);

        RecordStockMovement::run($variant, StockMovementType::Damage, -50);
    }

    public function test_a_rejected_negative_stock_movement_never_touches_stock_or_the_ledger(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        try {
            RecordStockMovement::run($variant, StockMovementType::Damage, -50);
        } catch (NegativeStockException) {
            // expected
        }

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(0, $variant->stockMovements()->count());
    }

    public function test_a_movement_landing_exactly_on_zero_stock_is_allowed(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        RecordStockMovement::run($variant, StockMovementType::Sale, -10);

        $this->assertSame(0, $variant->fresh()->stock);
    }

    /**
     * Bug hunt regression: the negative-stock guard used to read
     * `$variant->stock` from PHP memory with no row lock, while the
     * decrement itself was a separate atomic SQL statement — two
     * concurrent calls for the same variant could each read the same
     * pre-decrement stock, both pass the check, and both apply, leaving
     * stock negative despite each individually "validating"
     * non-negativity. True parallel threads aren't exercisable against
     * this suite's SQLite connection, so — matching this file's existing
     * `test_concurrent_checkout_on_last_unit_prevents_overselling`
     * convention — this proves the same invariant sequentially: stock
     * only ever depletes to exactly zero and never below it, however many
     * decrement attempts land at the boundary.
     */
    public function test_stock_never_goes_negative_across_repeated_decrements_at_the_boundary(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 1]);

        RecordStockMovement::run($variant, StockMovementType::Sale, -1);

        $rejectedCount = 0;

        for ($i = 0; $i < 4; $i++) {
            try {
                RecordStockMovement::run($variant->fresh(), StockMovementType::Sale, -1);
            } catch (NegativeStockException) {
                $rejectedCount++;
            }
        }

        $this->assertSame(4, $rejectedCount);
        $this->assertSame(0, $variant->fresh()->stock);
    }

    /**
     * AdjustStockWithReservationCheck reads $variant->stock immediately
     * after RecordStockMovement returns and expects the new value —
     * locking against a separately-fetched row must not leave the
     * caller's own passed-in instance stale.
     */
    public function test_the_passed_in_variant_instances_stock_reflects_the_new_value_immediately(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        RecordStockMovement::run($variant, StockMovementType::Restock, 5);

        $this->assertSame(15, $variant->stock);
    }

    public function test_reservation_creation_respects_available_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);

        $reservation = ReserveStockForOrder::run($variant, 3, Order::factory()->create());

        $this->assertSame(3, $reservation->quantity);
        $this->assertSame(StockReservationStatus::Active, $reservation->status);
    }

    public function test_reserving_a_zero_quantity_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);

        $this->expectException(InvalidReservationQuantityException::class);

        ReserveStockForOrder::run($variant, 0, Order::factory()->create());
    }

    public function test_reserving_a_negative_quantity_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);

        $this->expectException(InvalidReservationQuantityException::class);

        ReserveStockForOrder::run($variant, -1, Order::factory()->create());
    }

    public function test_a_rejected_zero_quantity_reservation_never_touches_the_database(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);

        try {
            ReserveStockForOrder::run($variant, 0, Order::factory()->create());
        } catch (InvalidReservationQuantityException) {
            // expected
        }

        $this->assertSame(0, StockReservation::query()->where('product_variant_id', $variant->id)->count());
    }

    public function test_reservation_fails_when_it_would_exceed_available_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);

        ReserveStockForOrder::run($variant, 5, Order::factory()->create());

        $this->expectException(InsufficientStockException::class);

        // Nothing left after the first reservation consumed all 5 units.
        ReserveStockForOrder::run($variant, 1, Order::factory()->create());
    }

    /**
     * SQLite (the in-memory test database) has no row-level locking and
     * doesn't log BEGIN/COMMIT as queries (they go through PDO directly),
     * so the transaction/lock mechanism can't be observed behaviorally here
     * — the actual serialization guarantee is proven behaviorally instead
     * by the "last unit" test below. This is a guard rail against someone
     * silently dropping the lock or the transaction wrapper, in the same
     * spirit as this suite's migration-linting source checks.
     */
    public function test_reservation_creation_uses_row_locking_inside_a_transaction(): void
    {
        $source = (string) file_get_contents(app_path('Actions/Inventory/ReserveStockForOrder.php'));

        $this->assertStringContainsString('lockForUpdate()', $source);
        $this->assertStringContainsString('DB::transaction(', $source);
        $this->assertTrue(strpos($source, 'DB::transaction(') < strpos($source, 'lockForUpdate()'));
    }

    /**
     * True cross-connection concurrency can't be exercised against the
     * in-memory SQLite database used by the test suite (each connection
     * would get its own isolated database). This instead proves the
     * invariant the locking mechanism exists to guarantee: once a
     * reservation has consumed the last available unit, every subsequent
     * attempt for that variant is rejected, never double-booked.
     */
    public function test_concurrent_checkout_on_last_unit_prevents_overselling(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 1]);

        ReserveStockForOrder::run($variant, 1, Order::factory()->create());

        $rejectedCount = 0;

        for ($i = 2; $i <= 5; $i++) {
            try {
                ReserveStockForOrder::run($variant, 1, Order::factory()->create());
            } catch (InsufficientStockException) {
                $rejectedCount++;
            }
        }

        $this->assertSame(4, $rejectedCount);
        $this->assertSame(1, StockReservation::query()->where('status', StockReservationStatus::Active)->sum('quantity'));
    }

    public function test_reservation_window_is_configurable_via_store_settings(): void
    {
        StoreSetting::current()->update(['stock_reservation_minutes' => 45]);

        $variant = ProductVariant::factory()->create(['stock' => 5]);

        $reservation = ReserveStockForOrder::run($variant, 1, Order::factory()->create());

        $this->assertTrue($reservation->expires_at->between(now()->addMinutes(44), now()->addMinutes(46)));
    }

    public function test_reservation_expires_and_releases_stock_after_window(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);

        $expired = StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'status' => StockReservationStatus::Active,
            'expires_at' => now()->subMinute(),
        ]);

        $stillActive = StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'status' => StockReservationStatus::Active,
            'expires_at' => now()->addMinutes(10),
        ]);

        ReleaseExpiredReservations::run();

        $this->assertSame(StockReservationStatus::Released, $expired->fresh()->status);
        $this->assertSame(StockReservationStatus::Active, $stillActive->fresh()->status);
    }

    public function test_manual_adjustment_below_reserved_stock_is_permitted(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $user = User::factory()->create();

        StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 8,
            'status' => StockReservationStatus::Active,
        ]);

        // Physical count comes in lower than the ledger believed — must still proceed.
        $result = AdjustStockWithReservationCheck::run($variant, -9, $user, 'Physical count correction');

        $this->assertSame(1, $variant->fresh()->stock);
        $this->assertNotEmpty($result['at_risk_reservation_ids']);
    }

    public function test_uncovered_reservations_flagged_at_risk_after_adjustment(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $user = User::factory()->create();

        $reservation = StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 8,
            'status' => StockReservationStatus::Active,
        ]);

        AdjustStockWithReservationCheck::run($variant, -9, $user);

        $this->assertSame(StockReservationStatus::AtRisk, $reservation->fresh()->status);
    }

    public function test_adjustment_that_still_covers_reservations_does_not_flag_them(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $user = User::factory()->create();

        $reservation = StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 3,
            'status' => StockReservationStatus::Active,
        ]);

        AdjustStockWithReservationCheck::run($variant, -2, $user);

        $this->assertSame(StockReservationStatus::Active, $reservation->fresh()->status);
    }

    public function test_stock_cache_matches_sum_of_movements(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 0]);

        RecordStockMovement::run($variant, StockMovementType::Restock, 20);
        RecordStockMovement::run($variant, StockMovementType::Sale, -5);
        RecordStockMovement::run($variant, StockMovementType::Damage, -2);

        $this->assertSame(13, $variant->fresh()->stock);
        $this->assertSame(13, $variant->stockMovements()->sum('quantity'));
    }
}
