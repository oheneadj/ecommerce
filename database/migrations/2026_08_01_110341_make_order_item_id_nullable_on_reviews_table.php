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
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['order_item_id']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreignId('order_item_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreignId('order_item_id')->nullable(false)->change();
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->unique('order_item_id');
        });
    }
};
