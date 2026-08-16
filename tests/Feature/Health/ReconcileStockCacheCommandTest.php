<?php

/**
 * Covers the `health:reconcile-stock-cache` command — production
 * remediation for a StockCacheMatchesMovements (System Health, Tier 3)
 * failure.
 */

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileStockCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_no_mismatches_when_the_cache_already_matches(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        StockMovement::factory()->create(['product_variant_id' => $variant->id, 'type' => StockMovementType::Restock, 'quantity' => 10]);

        $this->artisan('health:reconcile-stock-cache')
            ->expectsOutputToContain('No mismatches found')
            ->assertExitCode(0);
    }

    public function test_a_dry_run_reports_the_mismatch_but_writes_nothing(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 25]);

        $this->artisan('health:reconcile-stock-cache')
            ->expectsOutputToContain('dry run')
            ->assertExitCode(0);

        $this->assertSame(25, $variant->fresh()->stock);
        $this->assertSame(0, StockMovement::where('product_variant_id', $variant->id)->count());
    }

    public function test_force_with_confirmation_backfills_the_movement_without_touching_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 25]);

        $this->artisan('health:reconcile-stock-cache --force')
            ->expectsConfirmation('About to insert 1 backfilling stock_movements row(s), as shown above. product_variants.stock is not touched. Continue?', 'yes')
            ->assertExitCode(0);

        $this->assertSame(25, $variant->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'type' => StockMovementType::Adjustment->value,
            'quantity' => 25,
        ]);
    }

    public function test_force_without_confirmation_writes_nothing(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 25]);

        $this->artisan('health:reconcile-stock-cache --force')
            ->expectsConfirmation('About to insert 1 backfilling stock_movements row(s), as shown above. product_variants.stock is not touched. Continue?', 'no')
            ->assertExitCode(0);

        $this->assertSame(0, StockMovement::where('product_variant_id', $variant->id)->count());
    }

    public function test_a_negative_drift_backfills_a_negative_quantity(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);
        StockMovement::factory()->create(['product_variant_id' => $variant->id, 'type' => StockMovementType::Restock, 'quantity' => 20]);

        $this->artisan('health:reconcile-stock-cache --force')
            ->expectsConfirmation('About to insert 1 backfilling stock_movements row(s), as shown above. product_variants.stock is not touched. Continue?', 'yes')
            ->assertExitCode(0);

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertSame(5, StockMovement::where('product_variant_id', $variant->id)->sum('quantity'));
    }
}
