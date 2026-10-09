<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->string('status', 20)->default('active');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamps();

            // The mailing list is keyed by address alone, so the address is the
            // natural key. Addresses are normalised to lower case on save, which
            // -- together with the case-insensitive default collation -- makes
            // "Jane@Example.com" and "jane@example.com" one and the same
            // subscriber rather than two.
            $table->unique('email');

            // Sending a campaign filters on status.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
