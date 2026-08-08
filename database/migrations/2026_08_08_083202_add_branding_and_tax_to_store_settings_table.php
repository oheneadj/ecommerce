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
     * Epic E13 (Store Settings & Branding) — lets a deployment reskin the
     * storefront and PDF receipts to a specific business without any code
     * change, and gives checkout a real (if simple, single-jurisdiction)
     * tax rate instead of the hardcoded 0 it had before.
     */
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('business_name')->nullable()->after('id');
            $table->string('logo_path')->nullable()->after('business_name');
            $table->string('primary_color')->nullable()->after('logo_path');
            $table->string('secondary_color')->nullable()->after('primary_color');
            $table->string('tagline')->nullable()->after('secondary_color');
            $table->string('contact_email')->nullable()->after('tagline');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->string('contact_address')->nullable()->after('contact_phone');
            // Whole-number percent, matching Coupon's existing Percentage
            // convention (no fractional precision) — e.g. 15 = 15% VAT.
            $table->unsignedSmallInteger('tax_rate')->default(0)->after('contact_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'business_name',
                'logo_path',
                'primary_color',
                'secondary_color',
                'tagline',
                'contact_email',
                'contact_phone',
                'contact_address',
                'tax_rate',
            ]);
        });
    }
};
