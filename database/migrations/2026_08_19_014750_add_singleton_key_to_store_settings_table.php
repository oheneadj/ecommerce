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
            // Every row gets the same fixed value — the unique index is
            // what actually enforces "only one store_settings row can
            // ever exist", closing a race StoreSetting::current()'s
            // firstOrCreate([]) couldn't close on its own (SELECT-then-
            // INSERT is not atomic; two concurrent first-touch requests
            // against an empty table could both insert). The loser of
            // that race now hits this constraint and re-fetches the
            // winner's row instead of creating a second, orphaned one.
            $table->string('singleton_key')->default('singleton')->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn('singleton_key');
        });
    }
};
