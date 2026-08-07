<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * reviews.order_item_id is already nullable (made nullable earlier this
 * session so DeleteReview could free it for reuse) but its FK still had no
 * explicit delete behavior — deleting an OrderItem that has a review threw
 * a raw QueryException instead of nulling the reference, which is the only
 * behavior that actually makes sense for an already-nullable column.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['order_item_id']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['order_item_id']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreign('order_item_id')->references('id')->on('order_items');
        });
    }
};
