<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes every remaining foreign key's delete behavior explicit — previously
 * these had none, which just meant "restrict" implicitly at the DB level,
 * but as a raw, unhandled QueryException the moment it fired rather than a
 * deliberate, self-documenting choice. `reviews.order_item_id` is handled
 * by a separate migration since it needs `nullOnDelete()`, not restrict.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, column: string, references: string, behavior: 'restrict'|'cascade'}>
     */
    private array $foreignKeys = [
        ['table' => 'products', 'column' => 'category_id', 'references' => 'categories', 'behavior' => 'restrict'],
        ['table' => 'product_variants', 'column' => 'product_id', 'references' => 'products', 'behavior' => 'cascade'],
        ['table' => 'payments', 'column' => 'order_id', 'references' => 'orders', 'behavior' => 'restrict'],
        ['table' => 'refunds', 'column' => 'payment_id', 'references' => 'payments', 'behavior' => 'restrict'],
        ['table' => 'shipments', 'column' => 'order_id', 'references' => 'orders', 'behavior' => 'restrict'],
        ['table' => 'shipments', 'column' => 'shipping_method_id', 'references' => 'shipping_methods', 'behavior' => 'restrict'],
        ['table' => 'coupon_usages', 'column' => 'coupon_id', 'references' => 'coupons', 'behavior' => 'restrict'],
        ['table' => 'coupon_usages', 'column' => 'order_id', 'references' => 'orders', 'behavior' => 'restrict'],
        ['table' => 'stock_movements', 'column' => 'product_variant_id', 'references' => 'product_variants', 'behavior' => 'restrict'],
        ['table' => 'reviews', 'column' => 'product_id', 'references' => 'products', 'behavior' => 'restrict'],
        ['table' => 'reviews', 'column' => 'user_id', 'references' => 'users', 'behavior' => 'restrict'],
        ['table' => 'wishlist_items', 'column' => 'product_variant_id', 'references' => 'product_variants', 'behavior' => 'cascade'],
        ['table' => 'stock_reservations', 'column' => 'product_variant_id', 'references' => 'product_variants', 'behavior' => 'restrict'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->foreignKeys as $fk) {
            Schema::table($fk['table'], function (Blueprint $table) use ($fk): void {
                $table->dropForeign([$fk['column']]);
            });

            Schema::table($fk['table'], function (Blueprint $table) use ($fk): void {
                $foreign = $table->foreign($fk['column'])->references('id')->on($fk['references']);

                $fk['behavior'] === 'cascade' ? $foreign->cascadeOnDelete() : $foreign->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->foreignKeys as $fk) {
            Schema::table($fk['table'], function (Blueprint $table) use ($fk): void {
                $table->dropForeign([$fk['column']]);
            });

            Schema::table($fk['table'], function (Blueprint $table) use ($fk): void {
                $table->foreign($fk['column'])->references('id')->on($fk['references']);
            });
        }
    }
};
