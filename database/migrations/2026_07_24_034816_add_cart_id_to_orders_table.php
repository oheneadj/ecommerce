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
     * A cart converts into at most one order, ever — the unique constraint
     * is what CreateOrderFromCart relies on to guarantee a duplicate
     * checkout submission (double-click, back-button resubmit) returns the
     * already-created order instead of creating a second one.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('cart_id')->nullable()->unique()->after('id')->constrained('carts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cart_id');
        });
    }
};
