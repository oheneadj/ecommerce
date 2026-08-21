<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * orders.created_at and product_variants.stock had no index despite being
 * filtered/sorted in the app's hottest queries — the dashboard's date-range
 * metrics, the storefront product listing/search, and low-stock reporting.
 * Composite (not standalone) so each index also covers the existing
 * status-only lookups these tables already do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(['status', 'stock']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['status', 'stock']);
        });
    }
};
