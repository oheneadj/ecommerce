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
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons');
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Enforces usage_limit_per_user for guests, matched by email.
            $table->string('guest_email')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['coupon_id', 'user_id']);
            $table->index(['coupon_id', 'guest_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
