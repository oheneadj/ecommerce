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
        Schema::table('addresses', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->after('id');
        });

        DB::table('addresses')->whereNull('ulid')->orderBy('id')->each(function ($address) {
            DB::table('addresses')->where('id', $address->id)->update(['ulid' => (string) Str::ulid()]);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->ulid('ulid')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });
    }
};
