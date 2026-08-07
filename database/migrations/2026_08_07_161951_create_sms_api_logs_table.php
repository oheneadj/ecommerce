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
     * Records an outbound call to an SMS provider that genuinely occurred
     * (OTP delivery or an ad-hoc staff-composed message) — mirrors
     * payment_api_logs' role for payment-gateway calls (CLAUDE.md §21:
     * every third-party API call must be logged in full to a dedicated
     * table, never to laravel.log). Not order-scoped — an OTP send has no
     * order at all.
     */
    public function up(): void
    {
        Schema::create('sms_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('action');
            $table->string('recipient');
            // text, not json — these columns hold Laravel's `encrypted:array`
            // cast (an encrypted string blob), since an OTP send's message
            // body embeds the plaintext code (CLAUDE.md §21).
            $table->text('request_payload');
            $table->text('response_payload')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_api_logs');
    }
};
