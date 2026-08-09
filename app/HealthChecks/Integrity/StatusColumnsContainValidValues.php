<?php

/**
 * Tier 3 (data integrity) — CRITICAL: every status/type column holds a
 * value from its enum class.
 */

declare(strict_types=1);

namespace App\HealthChecks\Integrity;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Enums\ShipmentStatus;
use App\Enums\StockReservationStatus;
use App\Enums\VariantStatus;
use Illuminate\Support\Facades\DB;

/**
 * These are `string` columns per CLAUDE.md §6 (MySQL `enum` is banned), so
 * the database does not constrain them at all — a raw update or a manual
 * DB edit can insert any string. Eloquent's own enum cast would throw
 * rather than silently hydrate an invalid value, so this queries the raw
 * column directly instead of going through the model.
 */
class StatusColumnsContainValidValues implements IntegrityCheck
{
    /** @var array<int, array{table: string, column: string, enum: class-string<\UnitEnum>}> */
    private const CHECKED_COLUMNS = [
        ['table' => 'orders', 'column' => 'status', 'enum' => OrderStatus::class],
        ['table' => 'payments', 'column' => 'status', 'enum' => PaymentStatus::class],
        ['table' => 'products', 'column' => 'status', 'enum' => ProductStatus::class],
        ['table' => 'product_variants', 'column' => 'status', 'enum' => VariantStatus::class],
        ['table' => 'reviews', 'column' => 'status', 'enum' => ReviewStatus::class],
        ['table' => 'stock_reservations', 'column' => 'status', 'enum' => StockReservationStatus::class],
        ['table' => 'shipments', 'column' => 'status', 'enum' => ShipmentStatus::class],
        ['table' => 'order_status_histories', 'column' => 'status', 'enum' => OrderStatus::class],
        ['table' => 'coupons', 'column' => 'type', 'enum' => CouponType::class],
    ];

    public function name(): string
    {
        return 'Status columns contain valid values';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function remediationHint(): string
    {
        return 'A status/type column holds a value that does not exist on its enum — find and fix it with a manual UPDATE, then find the write path that bypassed the Action layer.';
    }

    public function run(): IntegrityCheckOutcome
    {
        $allInvalidIds = [];

        foreach (self::CHECKED_COLUMNS as $checked) {
            /** @var array<int, string> $validValues */
            $validValues = array_map(fn ($case) => $case->value, $checked['enum']::cases());

            $invalidIds = DB::table($checked['table'])
                ->whereNotIn($checked['column'], $validValues)
                ->pluck('id')
                ->all();

            $allInvalidIds = [...$allInvalidIds, ...$invalidIds];
        }

        return $allInvalidIds === [] ? IntegrityCheckOutcome::clean() : IntegrityCheckOutcome::violations($allInvalidIds);
    }
}
