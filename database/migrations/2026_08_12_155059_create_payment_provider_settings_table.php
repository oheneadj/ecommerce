<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment provider is no longer a single Super-Admin-chosen "active"
     * setting — multiple providers can be enabled simultaneously, ordered,
     * and the customer picks one at checkout. One row per known provider,
     * never admin-created (auto-seeded from the `PaymentProvider` enum via
     * `PaymentProviderSetting::syncKnownProviders()`), so no `ulid` — there
     * is no per-record route to protect from raw-id exposure, same as
     * `product_images`.
     */
    public function up(): void
    {
        Schema::create('payment_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Best-effort carry-over: a Super Admin may already have set a
        // single active provider under the previous single-provider
        // design — preserve it as the one enabled, first-ordered row
        // rather than silently resetting everyone to "nothing enabled."
        $previousActiveProvider = DB::table('store_settings')->value('active_payment_provider');

        if ($previousActiveProvider !== null) {
            DB::table('payment_provider_settings')->insert([
                'provider' => $previousActiveProvider,
                'enabled' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn('active_payment_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('active_payment_provider')->nullable()->after('whatsapp_url');
        });

        Schema::dropIfExists('payment_provider_settings');
    }
};
