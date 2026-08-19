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
            // The app's own clock/storage stays UTC everywhere (CLAUDE.md's
            // "store in UTC, convert only for display" rule) — this is
            // purely the timezone customer-facing order/invoice timestamps
            // are converted to at render time, since this app has no
            // per-customer timezone concept and a fixed operating region
            // per deployed store is the right level for a template used
            // across different businesses/regions.
            $table->string('timezone')->default('UTC')->after('tax_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
