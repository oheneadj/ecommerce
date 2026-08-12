<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `checkout_mode` only carries meaning for Paystack today (Redirect vs
     * Popup) — a plain nullable column rather than a generic per-provider
     * JSON settings blob, since no other provider needs a mode choice yet
     * and Moolre's request-to-pay flow has no browser UI to vary at all.
     * A future provider that genuinely needs its own per-provider config
     * can justify a JSON column then, not speculatively now.
     */
    public function up(): void
    {
        Schema::table('payment_provider_settings', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('provider');
            $table->text('description')->nullable()->after('logo_path');
            $table->string('checkout_mode')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('payment_provider_settings', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'description', 'checkout_mode']);
        });
    }
};
