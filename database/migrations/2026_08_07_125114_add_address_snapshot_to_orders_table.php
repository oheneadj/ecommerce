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
        Schema::table('orders', function (Blueprint $table): void {
            // Freezes the shipping address at checkout time, same rule as
            // OrderItem.item_snapshot — a past order must never change how
            // it displays just because the live Address was later edited
            // or deleted.
            $table->json('address_snapshot')->nullable()->after('address_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['address_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('address_id')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('address_id')->references('id')->on('addresses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['address_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('address_id')->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('address_id')->references('id')->on('addresses');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('address_snapshot');
        });
    }
};
