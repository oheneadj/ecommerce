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
     * Records an outbound call to a payment provider that genuinely
     * occurred. Never written inside the same transaction as business
     * processing — see technical-design-ecommerce.md §4g's audit-log
     * exemption: a rollback here would erase evidence that a charge was
     * genuinely attempted, even if our own bookkeeping then fails.
     */
    public function up(): void
    {
        Schema::create('payment_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider');
            $table->string('action');
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_api_logs');
    }
};
