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
     * One row per (announcement, viewer) — never a raw per-page-load event
     * log, so this is a reach count, not an impression count. `viewer_key`
     * covers both logged-in customers ("user_{id}") and guests (the same
     * session-id convention ResolveCurrentCart::guestSessionId() already
     * uses for guest carts), so reach numbers work identically for both.
     * `viewed_at` is written once, the first time this viewer is shown the
     * announcement. `dismissed_at` only ever gets set for a 'popup'-type
     * announcement (App\Livewire\Storefront\AnnouncementPopup) — a
     * 'banner' stays visible to everyone it targets for as long as its own
     * schedule/active flag says it should; a visitor can't opt out of that
     * one, only a popup.
     */
    public function up(): void
    {
        Schema::create('announcement_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->string('viewer_key');
            $table->timestamp('viewed_at');
            $table->timestamp('dismissed_at')->nullable();

            $table->unique(['announcement_id', 'viewer_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_views');
    }
};
