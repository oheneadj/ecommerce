<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfills the real FK constraint deferred from the Inventory sprint,
     * now that the `orders` table exists.
     */
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
    }
};
