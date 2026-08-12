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
            $table->string('facebook_url')->nullable()->after('contact_address');
            $table->string('instagram_url')->nullable()->after('facebook_url');
            $table->string('x_url')->nullable()->after('instagram_url');
            $table->string('tiktok_url')->nullable()->after('x_url');
            $table->string('whatsapp_url')->nullable()->after('tiktok_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['facebook_url', 'instagram_url', 'x_url', 'tiktok_url', 'whatsapp_url']);
        });
    }
};
