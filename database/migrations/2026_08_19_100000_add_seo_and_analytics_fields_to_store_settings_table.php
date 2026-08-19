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
            $table->string('address_street')->nullable()->after('contact_address');
            $table->string('address_city')->nullable()->after('address_street');
            $table->string('address_region')->nullable()->after('address_city');
            $table->string('address_postal_code')->nullable()->after('address_region');
            $table->string('address_country')->default('GH')->after('address_postal_code');
            // double, not decimal — decimal is reserved for money (integer
            // minor units per CLAUDE.md §13, enforced by
            // MigrationLintingTest); lat/long is a plain float coordinate.
            $table->double('latitude')->nullable()->after('address_country');
            $table->double('longitude')->nullable()->after('latitude');
            $table->string('ga_measurement_id')->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'address_street',
                'address_city',
                'address_region',
                'address_postal_code',
                'address_country',
                'latitude',
                'longitude',
                'ga_measurement_id',
            ]);
        });
    }
};
