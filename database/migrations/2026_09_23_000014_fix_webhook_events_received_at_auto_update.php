<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MariaDB gave webhook_events.received_at an implicit
     * "ON UPDATE CURRENT_TIMESTAMP" (first non-nullable timestamp column),
     * which would silently overwrite the received timestamp whenever the
     * row is updated (e.g. when processed_at/status are set later).
     * Providing an explicit DEFAULT CURRENT_TIMESTAMP (with no on-update
     * clause) stops the implicit auto-update while keeping the default.
     */
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->timestamp('received_at')->useCurrent()->change();
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->change();
        });
    }
};