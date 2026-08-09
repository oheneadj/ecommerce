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
     * `shipping_total` already existed but was never actually wired to a
     * chosen shipping method — checkout always passed 0. `shipping_method_id`
     * is nullable + nullOnDelete (a method can be deactivated/deleted after
     * the order exists) and `shipping_method_name` snapshots the name at
     * order time, same historical-snapshot rule as `address_snapshot`/
     * `item_snapshot` — a later rename must never change how a past order
     * displays.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_method_id')->nullable()->after('address_id')->constrained('shipping_methods')->nullOnDelete();
            $table->string('shipping_method_name')->nullable()->after('shipping_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn('shipping_method_name');
        });
    }
};
