<?php

/**
 * Tier 1 (config/schema) — asserts the highest-risk relationships from the
 * technical design (docs/technical-design-ecommerce.md §3) are real FK
 * constraints, not bare unconstrained integer columns.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * A bare `unsignedBigInteger` looks identical to a foreign key in an ERD
 * while enforcing nothing. This checks the small set of relationships
 * whose loss of referential integrity would corrupt financial/inventory
 * records — not every relationship in the schema, which would make this
 * check as fragile as the migrations themselves.
 */
class ForeignKeysAreEnforced extends Check
{
    /** @var array<int, array{table: string, column: string}> */
    private const EXPECTED_FOREIGN_KEYS = [
        ['table' => 'order_items', 'column' => 'order_id'],
        ['table' => 'order_items', 'column' => 'product_variant_id'],
        ['table' => 'stock_reservations', 'column' => 'product_variant_id'],
        ['table' => 'stock_movements', 'column' => 'product_variant_id'],
        ['table' => 'payments', 'column' => 'order_id'],
        ['table' => 'refunds', 'column' => 'payment_id'],
        ['table' => 'cart_items', 'column' => 'cart_id'],
        ['table' => 'cart_items', 'column' => 'product_variant_id'],
    ];

    public function run(): Result
    {
        $result = Result::make();

        if (DB::connection()->getDriverName() !== 'mysql') {
            return $result->ok('Not applicable — the active connection is not MySQL.');
        }

        $constrainedColumns = collect(DB::select('
            SELECT table_name, column_name
            FROM information_schema.key_column_usage
            WHERE table_schema = DATABASE()
                AND referenced_table_name IS NOT NULL
        '))->map(fn ($row) => "{$row->table_name}.{$row->column_name}")->all();

        $missing = collect(self::EXPECTED_FOREIGN_KEYS)
            ->reject(fn (array $expected) => in_array("{$expected['table']}.{$expected['column']}", $constrainedColumns, true))
            ->map(fn (array $expected) => "{$expected['table']}.{$expected['column']}")
            ->values();

        if ($missing->isEmpty()) {
            return $result->ok('Every checked relationship has a real foreign key constraint.');
        }

        return $result
            ->failed("These columns have no foreign key constraint: {$missing->implode(', ')}. Fix: add ->foreignId(...)->constrained() in a new migration for each.")
            ->meta(['missing_foreign_keys' => $missing->all()]);
    }
}
