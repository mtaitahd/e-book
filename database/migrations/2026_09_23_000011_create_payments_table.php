<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('snippe');
            $table->string('payment_type')->default('mobile');
            $table->string('provider_reference')->nullable()->unique();
            $table->string('external_reference')->nullable();
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 8)->default('TZS');
            $table->string('status')->default('pending');
            $table->string('channel_provider')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};