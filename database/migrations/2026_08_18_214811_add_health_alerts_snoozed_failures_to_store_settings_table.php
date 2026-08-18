<?php

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
        Schema::table('store_settings', function (Blueprint $table) {
            // The failing-check labels captured at the moment an admin
            // snoozed alerts, so SendCriticalHealthAlert can tell "still
            // just the thing I already snoozed" apart from "something new
            // broke too" — a plain 24-hour timestamp alone can't.
            $table->json('health_alerts_snoozed_failures')->nullable()->after('health_alerts_snoozed_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn('health_alerts_snoozed_failures');
        });
    }
};
