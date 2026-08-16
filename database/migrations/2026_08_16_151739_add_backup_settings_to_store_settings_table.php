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
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('active_remote_storage_provider')->nullable()->after('active_sms_provider');
            $table->boolean('backup_auto_enabled')->default(false)->after('active_remote_storage_provider');
            $table->string('backup_frequency')->nullable()->after('backup_auto_enabled');
            $table->unsignedSmallInteger('backup_retention_days')->default(30)->after('backup_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'active_remote_storage_provider',
                'backup_auto_enabled',
                'backup_frequency',
                'backup_retention_days',
            ]);
        });
    }
};
