<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every confirmation is kept (not upserted) — a full audit trail of
     * who confirmed what, and when, is the point of an attestation.
     */
    public function up(): void
    {
        Schema::create('health_attestations', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->foreignId('confirmed_by')->constrained('users');
            $table->timestamp('confirmed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['key', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_attestations');
    }
};
