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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('order_number')->unique();
            // Never auto-populated from a matching guest_email — see ClaimGuestOrder.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->foreignId('address_id')->constrained('addresses');
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->integer('subtotal');
            $table->integer('discount_total')->default(0);
            $table->integer('tax_total')->default(0);
            $table->integer('shipping_total')->default(0);
            $table->integer('grand_total');
            $table->string('invoice_path')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
