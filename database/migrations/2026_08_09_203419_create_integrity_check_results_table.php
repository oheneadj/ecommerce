<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per Tier 3 check, updated in place on every nightly run —
     * this is a "last known result", not a history log (Spatie's own
     * health_check_result_history_items already covers Tier 1/2 history).
     */
    public function up(): void
    {
        Schema::create('integrity_check_results', function (Blueprint $table) {
            $table->id();
            $table->string('check_name')->unique();
            $table->string('severity');
            $table->string('status');
            $table->unsignedInteger('violation_count')->default(0);
            $table->json('sample_ids')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('ran_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrity_check_results');
    }
};
