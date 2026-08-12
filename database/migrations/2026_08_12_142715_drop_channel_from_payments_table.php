<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * `channel` only ever tracked the checkout-time mobile_money/card
     * choice, now replaced by a single admin-selected active provider
     * (`store_settings.active_payment_provider`) — `provider` already
     * carries the meaningful information this column duplicated.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('channel')->nullable();
        });
    }
};
