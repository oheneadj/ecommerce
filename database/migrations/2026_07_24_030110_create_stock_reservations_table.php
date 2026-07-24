<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            // No FK yet: the `orders` table doesn't exist until the Checkout
            // sprint. Add `->foreign('order_id')->references('id')->on('orders')`
            // in that sprint's migration once the table exists.
            $table->unsignedBigInteger('order_id')->nullable();
            $table->integer('quantity');
            $table->string('status')->default('active');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['expires_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
