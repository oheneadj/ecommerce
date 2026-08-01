<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table): void {
            $table->ulid('ulid')->nullable()->after('id');
        });

        // Backfill existing rows before enforcing uniqueness — ShippingMethod
        // previously had no route-model-binding key besides its raw bigint id.
        foreach (DB::table('shipping_methods')->pluck('id') as $id) {
            DB::table('shipping_methods')->where('id', $id)->update(['ulid' => (string) Str::ulid()]);
        }

        Schema::table('shipping_methods', function (Blueprint $table): void {
            $table->string('ulid')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table): void {
            $table->dropColumn('ulid');
        });
    }
};
