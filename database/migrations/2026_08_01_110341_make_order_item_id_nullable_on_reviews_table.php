<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL refuses to drop a unique index that's still backing a foreign
     * key constraint, so the FK has to go first. The unique constraint is
     * restored afterward, not left dropped — `Review`/`SubmitReview` rely
     * on it as the authoritative guard against two concurrent submissions
     * for the same order item both passing the app-level existence check
     * (a real DB unique index still allows any number of NULLs, which is
     * exactly what `DeleteReview` needs when it nulls `order_item_id` out
     * to free the slot for a future review).
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['order_item_id']);
            $table->dropUnique(['order_item_id']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreignId('order_item_id')->nullable()->change();
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->unique('order_item_id');
            $table->foreign('order_item_id')->references('id')->on('order_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['order_item_id']);
            $table->dropUnique(['order_item_id']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreignId('order_item_id')->nullable(false)->change();
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->unique('order_item_id');
            $table->foreign('order_item_id')->references('id')->on('order_items');
        });
    }
};
