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
     * One row per backup attempt (DB + uploaded files together, see
     * App\Jobs\RunBackupJob) — status starts Pending/Running and is
     * transitioned to Success/Failed by App\Listeners\RecordSuccessfulBackup
     * / RecordFailedBackup, so unlike an append-only audit log
     * (payment_api_logs) this one genuinely gets updated.
     */
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->nullable();
            $table->string('remote_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
